<?php

namespace App\Console\Commands;

use App\Models\MysteryBox;
use App\Models\MysteryBoxAwardNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Services\SmsService;

class MysteryBoxAwardNotificationConsumer extends Command
{
    protected $signature = 'mystery-box:consume-awards';
    protected $description = 'Consume mystery box award notifications from RabbitMQ';

    private $boxAmounts;

    public function __construct()
    {
        parent::__construct();
        $this->boxAmounts = config('box_amounts');
    }

    public function handle()
    {
        $this->info('Starting Mystery Box Award Notification Consumer...');
        $this->info(sprintf('Boxes config: %s', json_encode(config('boxes.types'))));
        $this->info(sprintf('Box amounts config loaded: %s', json_encode($this->boxAmounts)));

        try {
            // Create RabbitMQ connection
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );

            $channel = $connection->channel();

            // Declare queue
            $queueName = config('queue_settings.mystery_box_award.consumer.queue_name');
            $channel->queue_declare($queueName, false, true, false, false);

            // Set prefetch count to 1 for fair distribution
            $channel->basic_qos(null, 1, null);

            $this->info('Waiting for mystery box award notifications...');

            // Define callback function
            $callback = function (AMQPMessage $message) {
                try {
                    $data = json_decode($message->getBody(), true);
                    
                    // Get amount based on box type
                    $amount = $this->boxAmounts[$data['box_type']] ?? 0;
                    
                    $this->info(sprintf(
                        'Processing mystery box award - MSISDN: %s, Box Type: %s, Amount: %d, Config: %s, Raw Message: %s',
                        $data['msisdn'],
                        $data['box_type'],
                        $amount,
                        json_encode($this->boxAmounts),
                        $message->getBody()
                    ));
                    
                    // Determine source based on request_id pattern
                    if (str_starts_with($data['request_id'], 'MYBXR')) {
                        $source = MysteryBox::SOURCE_RENEWAL;
                    } elseif (str_starts_with($data['request_id'], 'MMNYTXN')) {
                        $source = MysteryBox::SOURCE_MIXX_PAYMENT;
                    } else {
                        // Log unknown request_id pattern for investigation
                        Log::warning('Unknown request_id pattern for mystery box award', [
                            'request_id' => $data['request_id'],
                            'msisdn' => $data['msisdn'],
                            'box_type' => $data['box_type']
                        ]);
                        // Default to Mixx payment for unknown patterns
                        $source = MysteryBox::SOURCE_MIXX_PAYMENT;
                    }

                    // Save mystery box data
                    $mysteryBox = MysteryBox::create([
                        'msisdn' => $data['msisdn'],
                        'box_type' => $data['box_type'],
                        'request_id' => $data['request_id'],
                        'status' => $data['status'],
                        'amount' => $amount,
                        'source' => $source
                    ]);

                    // Send award notification if request_id does not start with "MYBXR" as it's renewal and will be handled separately
                    if (!str_starts_with($data['request_id'], 'MYBXR')) {
                        $this->sendAwardNotification($mysteryBox);
                    } else {
                        $this->info(sprintf(
                            'Skipping award notification for request_id starting with MYBXR: %s',
                            $data['request_id']
                        ));
                    }

                    // Update GT Portal
                    // $this->updateGTPortal($mysteryBox);

                    // Acknowledge message
                    $message->ack();

                    $this->info(sprintf(
                        'Processed mystery box award notification - MSISDN: %s, Box Type: %s, Amount: %d',
                        $data['msisdn'],
                        $data['box_type'],
                        $amount
                    ));
                } catch (\Exception $e) {
                    Log::error('Error processing mystery box award notification: ' . $e->getMessage());
                    $this->error('Error: ' . $e->getMessage());
                    
                    // Reject message and requeue
                    // $message->reject(true);
                }
            };

            // Start consuming
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // Close connection
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('Error in mystery box award notification consumer: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }

    private function sendAwardNotification(MysteryBox $mysteryBox)
    {
        // Use specific message for mixx_payment source
        if ($mysteryBox->source === MysteryBox::SOURCE_MIXX_PAYMENT) {
            $message = sprintf(
                config('messages.sms.mystery_box_award_mixx_payment'),
                $mysteryBox->box_type
            );
        } else {
            $message = sprintf(
                config('messages.sms.mystery_box_award'),
                $mysteryBox->box_type
            );
        }

        $smsService = new SmsService();
        $success = $smsService->sendSms($mysteryBox->msisdn, $message);

        if ($success) {
            Log::info("Award notification SMS sent successfully", [
                'msisdn' => $mysteryBox->msisdn,
                'box_type' => $mysteryBox->box_type,
                'source' => $mysteryBox->source
            ]);
        } else {
            Log::error("Failed to send award notification SMS", [
                'msisdn' => $mysteryBox->msisdn,
                'box_type' => $mysteryBox->box_type,
                'source' => $mysteryBox->source
            ]);
        }
    }

    private function updateGTPortal(MysteryBox $mysteryBox)
    {
        // TODO: Implement GT Portal update logic
        // This could be an API call to update available boxes
        Log::info(sprintf(
            'Updating GT Portal - MSISDN: %s, Box Type: %s, Amount: %d',
            $mysteryBox->msisdn,
            $mysteryBox->box_type,
            $mysteryBox->amount
        ));
    }
}