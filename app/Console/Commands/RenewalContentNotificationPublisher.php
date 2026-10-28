<?php

namespace App\Console\Commands;

use App\Models\RenewalContentNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RenewalContentNotificationPublisher extends Command
{
    protected $signature = 'renewal:content-notification-publisher';
    protected $description = 'Publish renewal content notifications to RabbitMQ queue';

    private $connection;
    private $channel;
    private $queueName;
    private $batchSize;

    public function handle()
    {
        try {
            $this->initializeConnection();

            $this->info('Starting renewal content notification publisher...');
            Log::info('RenewalContentNotificationPublisher started', [
                'execution_time' => date('Y-m-d H:i:s')
            ]);
            
            $totalPublished = 0;
            
            // Process all pending notifications in batches until none remain
            do {
                $publishedCount = $this->publishBatch();
                $totalPublished += $publishedCount;
                
                if ($publishedCount > 0) {
                    $this->info("Published {$publishedCount} renewal content notifications");
                    // Small delay between batches to avoid overwhelming the system
                    usleep(100000); // 0.1 second
                }
                
            } while ($publishedCount > 0);

            $this->info("Renewal content notification publisher completed. Total published: {$totalPublished}");
            Log::info('RenewalContentNotificationPublisher completed', [
                'total_published' => $totalPublished,
                'completion_time' => date('Y-m-d H:i:s')
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('Error in renewal content notification publisher: ' . $e->getMessage());
            Log::error('RenewalContentNotificationPublisher error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time' => date('Y-m-d H:i:s')
            ]);
            return 1;
        } finally {
            $this->closeConnection();
        }
    }

    private function publishBatch()
    {
        try {
            // Get unpublished notifications
            $notifications = RenewalContentNotification::where('publish_status', false)
                ->limit($this->batchSize)
                ->get();

            if ($notifications->isEmpty()) {
                return 0;
            }

            $publishedIds = [];

            foreach ($notifications as $notification) {
                // Create message
                $message = new AMQPMessage(
                    json_encode([
                        'msisdn' => $notification->msisdn,
                        'request_id' => $notification->request_id,
                        'payment_type' => $notification->payment_type,
                        'text' => $notification->text
                    ]),
                    ['delivery_mode' => 2] // Make message persistent
                );

                // Publish message
                $this->channel->basic_publish($message, '', $this->queueName);
                $publishedIds[] = $notification->id;

                $this->info(sprintf(
                    'Published renewal content notification - MSISDN: %s, Type: %s',
                    $notification->msisdn,
                    $notification->payment_type
                ));
            }

            // Bulk update all published notifications
            if (!empty($publishedIds)) {
                RenewalContentNotification::whereIn('id', $publishedIds)
                    ->update(['publish_status' => true]);
            }

            return count($publishedIds);

        } catch (\Exception $e) {
            Log::error('Error publishing renewal content notification batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function initializeConnection()
    {
        $publisherSettings = config('queue_settings.renewal_content_notification.publisher');
        
        $this->queueName = $publisherSettings['queue_name'];
        $this->batchSize = $publisherSettings['batch_size'];

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