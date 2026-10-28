<?php

namespace App\Console\Commands;

use App\Models\MysteryBoxAwardNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MysteryBoxAwardNotificationPublisher extends Command
{
    protected $signature = 'mystery-box:publish-awards {--batch-size=50}';
    protected $description = 'Publish mystery box award notifications to RabbitMQ';

    public function handle()
    {
        $this->info('Starting Mystery Box Award Notification Publisher...');

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
            $queueName = config('queue_settings.mystery_box_award.publisher.queue_name');
            $channel->queue_declare($queueName, false, true, false, false);

            $batchSize = $this->option('batch-size');
            $this->info(sprintf('Using batch size: %d', $batchSize));

            while (true) {
                try {
                    // Get unpublished notifications
                    $notifications = MysteryBoxAwardNotification::where('publish_status', false)
                        ->limit($batchSize)
                        ->get();

                    if ($notifications->isEmpty()) {
                        $this->info('No pending notifications found. Waiting...');
                        sleep(5);
                        continue;
                    }

                    $this->info(sprintf('Found %d pending notifications', $notifications->count()));

                    $publishedIds = [];
                    foreach ($notifications as $notification) {
                        // Create message
                        $message = new AMQPMessage(
                            json_encode([
                                'msisdn' => $notification->msisdn,
                                'box_type' => $notification->box_type,
                                'request_id' => $notification->request_id,
                                'status' => $notification->status
                            ]),
                            ['delivery_mode' => 2] // Make message persistent
                        );

                        // Publish message
                        $channel->basic_publish($message, '', $queueName);
                        $publishedIds[] = $notification->id;

                        $this->info(sprintf(
                            'Published notification - MSISDN: %s, Box Type: %s',
                            $notification->msisdn,
                            $notification->box_type
                        ));
                    }

                    // Bulk update all published notifications
                    if (!empty($publishedIds)) {
                        MysteryBoxAwardNotification::whereIn('id', $publishedIds)
                            ->update(['publish_status' => true]);
                    }

                    $this->info(sprintf('Successfully published %d notifications', count($publishedIds)));
                } catch (\Exception $e) {
                    Log::error('Error publishing mystery box award notifications: ' . $e->getMessage());
                    $this->error('Error: ' . $e->getMessage());
                    sleep(30); // Wait before retrying
                }
            }

            // Close connection
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('Error in mystery box award notification publisher: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }
} 