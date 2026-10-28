<?php

namespace App\Console\Commands;

use App\Models\SmsNotification;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class SmsNotificationPublisher extends Command
{
    protected $signature = 'sms:publish-notifications';
    protected $description = 'Publish SMS notifications to RabbitMQ queue';

    public function handle()
    {
        $this->info('Starting SMS Notification Publisher...');

        try {
            // Get queue settings
            $queueSettings = config('queue_settings.sms_notification.publisher');
            $queueName = $queueSettings['queue_name'];
            $batchSize = $queueSettings['batch_size'];

            // Create RabbitMQ connection
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );

            $channel = $connection->channel();

            // Declare queue
            $channel->queue_declare($queueName, false, true, false, false);

            $this->info('Waiting for new SMS notifications to publish...');

            while (true) {
                // Get unpublished notifications with batch size
                $notifications = SmsNotification::where('publish_status', false)
                    ->limit($batchSize)
                    ->get();

                if ($notifications->isNotEmpty()) {
                    $publishedIds = [];
                    foreach ($notifications as $notification) {
                        $message = new AMQPMessage(
                            json_encode([
                                'msisdn' => $notification->msisdn,
                                'message' => $notification->message,
                                'notification_id' => $notification->id
                            ]),
                            ['delivery_mode' => 2] // Make message persistent
                        );

                        $channel->basic_publish($message, '', $queueName);
                        $publishedIds[] = $notification->id;

                        $this->info(sprintf(
                            'Published SMS notification - MSISDN: %s, Message: %s',
                            $notification->msisdn,
                            $notification->message
                        ));
                    }

                    // Bulk update all published notifications
                    if (!empty($publishedIds)) {
                        SmsNotification::whereIn('id', $publishedIds)
                            ->update(['publish_status' => true]);
                    }

                    $this->info(sprintf('Published %d SMS notifications.', count($publishedIds)));
                }

                // Sleep for a short time before checking again
                sleep(1);
            }

            // Close connection (this will never be reached due to infinite loop)
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('Error in SMS notification publisher: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }
} 