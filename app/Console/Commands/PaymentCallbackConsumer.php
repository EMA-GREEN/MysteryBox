<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\MysteryBox;
use App\Http\Controllers\MysteryBoxController;
use App\Services\SmsService;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use App\Models\Subscriber;
use App\Models\PaymentNotification;

class PaymentCallbackConsumer extends Command
{
    protected $signature = 'payment:callback:consume {--consumer-id=1}';
    protected $description = 'Consume payment callbacks from RabbitMQ queue';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $prefetchCount;

    public function __construct()
    {
        parent::__construct();
        $queueSettings = config('queue_settings.payment_callback.consumer');
        $this->queueName = $queueSettings['queue_name'];
        $this->prefetchCount = $queueSettings['prefetch_count'];
    }

    protected function connect()
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->queueName, false, true, false, false);
            $this->channel->basic_qos(null, $this->prefetchCount, null);
        }
    }

    public function handle()
    {
        $consumerId = $this->option('consumer-id');
        $this->info(sprintf('Starting Payment Callback Consumer #%d...', $consumerId));

        try {
            $this->connect();

            $callback = function ($msg) use ($consumerId) {
                try {
                    $data = json_decode($msg->body, true);
                    
                    $this->info(sprintf(
                        'Consumer #%d processing callback for MSISDN: %s',
                        $consumerId,
                        $data['msisdn']
                    ));
                    
                    Log::info("Processing payment callback", $data);

                    // Validate required fields
                    if (!isset($data['msisdn']) || !isset($data['request_id']) || !isset($data['status'])) {
                        throw new \Exception(config('messages.payment.error.missing_fields'));
                    }

                    // Find the payment record
                    $payment = Payment::where('request_id', $data['request_id'])->first();
                    if (!$payment) {
                        $this->error(sprintf(
                            'Consumer #%d: Payment not found for request_id: %s. Discarding message.',
                            $consumerId,
                            $data['request_id']
                        ));
                        Log::error("Payment not found for callback. Discarding message.", ['request_id' => $data['request_id']]);
                        $msg->ack(); // Acknowledge and discard the message
                        return;
                    }

                    // Update payment record with callback details
                    $payment->status = $data['status'];
                    $payment->callback_status_code = $data['status'] === 'success' ? '0000' : '9999';
                    $payment->callback_description = $data['details'] ?? ($data['status'] === 'success' ? 
                        config('messages.payment.success.callback') : 
                        config('messages.payment.success.failed'));
                    $payment->callback_received_time = now();
                    $payment->billing_system = $data['billing_system'] ?? 'MAIN_ACCOUNT';
                    $payment->save();

                    if ($payment->status === 'success') {

                        // Update subscriber billing info
                        $subscriber = Subscriber::findOrCreate($payment->msisdn);
                        $subscriber->updateBillingInfo($payment->amount);

                        $mysteryBox = null;

                        if ($payment->payment_type === Payment::PAYMENT_TYPE_PURCHASE) {
                            // Create mystery box for purchase
                            $mysteryBox = MysteryBox::create([
                                'msisdn' => $payment->msisdn,
                                'box_type' => $payment->box_type,
                                'amount' => $payment->amount,
                                'request_id' => $payment->request_id,
                                'status' => 'pending',
                            ]);

                        } else {
                            // For PAYMENT_TYPE_OPEN, find the existing mystery box
                            if ($payment->box_id) {
                                $mysteryBox = MysteryBox::where('id', $payment->box_id)
                                    ->where('msisdn', $payment->msisdn)
                                    ->whereIn('status', ['pending', 'awarded'])
                                    ->first();
                            } else {
                                $mysteryBox = MysteryBox::where('msisdn', $payment->msisdn)
                                    ->whereIn('status', ['pending', 'awarded'])
                                    ->latest()
                                    ->first();
                            }

                            if ($mysteryBox) {
                                Log::info("Found mystery box for opening", [
                                    'msisdn' => $payment->msisdn,
                                    'payment_id' => $payment->id,
                                    'payment_type' => $payment->payment_type,
                                    'mystery_box_id' => $mysteryBox->id,
                                    'mystery_box_type' => $mysteryBox->box_type,
                                    'mystery_box_status' => $mysteryBox->status
                                ]);
                            } else {
                                // Send failure SMS
                                $errorMsg = config('messages.sms.no_mystery_box_found', 'No pending or awarded mystery box found for opening..');
                                (new SmsService)->sendSms(
                                    $payment->msisdn, 
                                    $errorMsg
                                );
                                Log::error($errorMsg, [
                                    'msisdn' => $payment->msisdn,
                                    'payment_id' => $payment->id,
                                    'payment_type' => $payment->payment_type
                                ]);
                            }
                        }

                        if($mysteryBox){
                            // Update box_id in payment if null
                            if ($payment->box_id === null) {
                                $payment->update(['box_id' => $mysteryBox->id]);
                            }

                            // Update box_id in payment notification if null
                            $paymentNotification = PaymentNotification::where('request_id', $payment->request_id)->first();
                            if ($paymentNotification && $paymentNotification->box_id === null) {
                                $paymentNotification->update(['box_id' => $mysteryBox->id]);
                            }

                            // Open the mystery box
                            $mysteryBoxController = new MysteryBoxController();
                            $mysteryBoxController->openMysteryBox($payment->msisdn, $mysteryBox->id);
                        }

                    } else {
                        if (strpos($payment->callback_description, 'low balance') !== false) {
                            (new SmsService)->sendSms(
                                $payment->msisdn, 
                                config('messages.sms.low_balance')
                            );
                        } else {
                            (new SmsService)->sendSms(
                                $payment->msisdn, 
                                config('messages.sms.payment_failed')
                            );
                        }
                    }

                    $msg->ack();
                } catch (\Exception $e) {
                    $this->error(sprintf(
                        'Consumer #%d error processing message: %s',
                        $consumerId,
                        $e->getMessage()
                    ));
                    Log::error("Error processing payment callback: " . $e->getMessage());
                    // $msg->reject();
                }
            };

            $this->channel->basic_consume($this->queueName, '', false, false, false, false, $callback);

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            $this->error(sprintf(
                'Consumer #%d error: %s',
                $consumerId,
                $e->getMessage()
            ));
            Log::error("Error in payment callback consumer: " . $e->getMessage());
        } finally {
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->close();
            }
            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        }
    }
} 