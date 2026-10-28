<?php

namespace App\Console\Commands;

use App\Models\RenewalPaymentCallback;
use App\Models\RenewalPayment;
use App\Models\RenewalSubscriber;
use App\Models\Subscriber;
use App\Models\MysteryBoxAwardNotification;
use App\Models\RenewalContentNotification;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RenewalPaymentCallbackConsumer extends Command
{
    protected $signature = 'renewal:payment-callback-consumer';
    protected $description = 'Consume renewal payment callbacks from RabbitMQ queue';

    private $connection;
    private $channel;
    private $queueName;
    private $prefetchCount;
    private $smsService;

    public function __construct()
    {
        parent::__construct();
        $this->smsService = new SmsService();
    }

    public function handle()
    {
        try {
            $this->initializeConnection();
            $this->info('Starting renewal payment callback consumer...');
            
            // Set prefetch count for fair distribution
            $this->channel->basic_qos(null, $this->prefetchCount, null);
            
            // Set up consumer
            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,
                false,
                false,
                false,
                [$this, 'processCallback']
            );

            $this->info('Waiting for renewal payment callbacks. To exit press CTRL+C');

            // Keep consuming messages
            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }

        } catch (\Exception $e) {
            $this->error('Error in renewal payment callback consumer: ' . $e->getMessage());
            Log::error('RenewalPaymentCallbackConsumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            $this->closeConnection();
        }
    }

    public function processCallback(AMQPMessage $message)
    {
        try {
            $data = json_decode($message->getBody(), true);
            
            if (!$data) {
                $this->error('Invalid JSON in message: ' . $message->getBody());
                $message->nack(false, false);
                return;
            }

            $this->info(sprintf(
                'Processing renewal payment callback - MSISDN: %s, Request ID: %s, Status: %s',
                $data['msisdn'] ?? 'N/A',
                $data['request_id'] ?? 'N/A',
                $data['status'] ?? 'N/A'
            ));

            // Find the renewal payment record
            $renewalPayment = RenewalPayment::where('request_id', $data['request_id'])->first();
            
            if (!$renewalPayment) {
                $this->error('Renewal payment not found for request_id: ' . $data['request_id']);
                $message->ack();
                return;
            }

            // Update renewal_payments table with callback response details
            $renewalPayment->update([
                'callback_status_code' => $data['status'] ?? null,
                'callback_description' => $data['details'] ?? null,
                'callback_received_time' => now(),
                'status' => $this->mapCallbackStatus($data['status'] ?? null)
            ]);

            $msisdn = $renewalPayment->msisdn;
            $amount = $renewalPayment->amount;
            $isSuccess = $this->isSuccessfulPayment($data['status'] ?? null);

            if ($isSuccess) {
                $this->handleSuccessfulPayment($msisdn, $amount);
            } else {
                $this->handleFailedPayment($msisdn);
            }

            $message->ack();
            $this->info('Successfully processed renewal payment callback');

        } catch (\Exception $e) {
            $this->error('Error processing renewal payment callback: ' . $e->getMessage());
            Log::error('RenewalPaymentCallbackConsumer processing error', [
                'message_body' => $message->getBody(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $message->nack(false, false);
        }
    }

    private function handleSuccessfulPayment($msisdn, $amount)
    {
        // Update renewal_subscribers table
        $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();
        if ($renewalSubscriber) {
            $renewalSubscriber->update([
                'last_renewal_billing' => now(),
                'retry_status' => 'OFF'
            ]);
        }

        // Update subscribers table
        $subscriber = Subscriber::where('msisdn', $msisdn)->first();
        if ($subscriber) {
            $subscriber->update([
                'last_renewal_billing' => now(),
                'total_renewal_billed_amount' => $subscriber->total_renewal_billed_amount + $amount
            ]);
        } else {
            // Create subscriber if not exists
            $subscriber = Subscriber::create([
                'msisdn' => $msisdn,
                'last_renewal_billing' => now(),
                'total_renewal_billed_amount' => $amount,
                'activity_status' => 'ACTIVE',
                'dnd_status' => 'OFF',
                'sub_channel' => 'SMS',
                'record_date' => now()
            ]);
        }

        // Create MysteryBoxAwardNotification
        $date = date('YmdHis');
        $requestId = "MYBXR" . $date . $msisdn;
        $boxType = 'Bronze';

        MysteryBoxAwardNotification::create([
            'msisdn' => $msisdn,
            'box_type' => $boxType,
            'request_id' => $requestId,
            'status' => 'awarded',
            'publish_status' => false
        ]);

        // Update subscriber's last_awarded_box
        $subscriber->last_awarded_box = $boxType;
        $subscriber->save();

        // Handle SMS notification based on time
        $this->handleSmsNotification($msisdn);

        $this->info(sprintf(
            'Successful payment processed for MSISDN: %s, Amount: %s, Box Type: %s',
            $msisdn,
            $amount,
            $boxType
        ));
    }

    private function handleFailedPayment($msisdn)
    {
        // Update renewal_subscribers table for failed payment
        $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();
        if ($renewalSubscriber) {
            $renewalSubscriber->update([
                'retry_status' => 'ON',
                'publish_status' => false
            ]);
        }

        $this->info(sprintf('Failed payment processed for MSISDN: %s', $msisdn));
    }

    private function handleSmsNotification($msisdn)
    {
        $currentHour = now()->hour;
        
        // Check if current time is between 08:00 and 23:59
        if ($currentHour >= 8 && $currentHour <= 23) {
            // Send SMS immediately
            $successMessage = config('messages.sms.success_renewal_sms', 'Hongera umezawadiwa nafasi ya kufungua sanduku la Shaba, Tuma neno FUNGUA na unaweza kujishindia zawaidi kemkem.');
            
            try {
                $this->smsService->sendSms($msisdn, $successMessage);
                $this->info(sprintf('Success renewal SMS sent to MSISDN: %s', $msisdn));
            } catch (\Exception $e) {
                $this->error(sprintf('Failed to send SMS to MSISDN: %s, Error: %s', $msisdn, $e->getMessage()));
                Log::error('Failed to send renewal success SMS', [
                    'msisdn' => $msisdn,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            // Insert into renewal_content_notifications for later sending
            $successMessage = config('messages.sms.success_renewal_sms', 'Hongera umezawadiwa nafasi ya kufungua sanduku la Bronze, Tuma neno FUNGUA na unaweza kujishindia zawaidi kemkem.');
            
            RenewalContentNotification::create([
                'msisdn' => $msisdn,
                'request_id' => 'RNWL' . date('YmdHis') . $msisdn,
                'payment_type' => 'renewal_success',
                'text' => $successMessage,
                'publish_status' => false
            ]);

            $this->info(sprintf('SMS notification queued for later sending to MSISDN: %s', $msisdn));
        }
    }

    private function mapCallbackStatus($callbackStatus)
    {
        // Map callback status to renewal payment status
        switch (strtolower($callbackStatus ?? '')) {
            case 'success':
            case 'successful':
            case '0':
                return RenewalPayment::STATUS_SUCCESS;
            case 'failed':
            case 'failure':
            case 'error':
                return RenewalPayment::STATUS_FAILED;
            case 'cancelled':
            case 'canceled':
                return RenewalPayment::STATUS_CANCELLED;
            default:
                return RenewalPayment::STATUS_PENDING;
        }
    }

    private function isSuccessfulPayment($callbackStatus)
    {
        return in_array(strtolower($callbackStatus ?? ''), ['success', 'successful', '0']);
    }

    private function initializeConnection()
    {
        $queueSettings = config('queue_settings.renewal_payment_callback.consumer');
        
        $this->queueName = $queueSettings['queue_name'];
        $this->prefetchCount = $queueSettings['prefetch_count'];

        $this->connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password')
        );

        $this->channel = $this->connection->channel();
        
        // Declare queue (make sure it exists)
        $this->channel->queue_declare(
            $this->queueName,
            false,
            true,
            false,
            false
        );
    }

    private function closeConnection()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
}