<?php

namespace App\Console\Commands;

use App\Models\MobileMoneyTransactionNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MobileMoneyTransactionNotificationPublisher extends Command
{
    protected $signature = 'mobile-money-transaction:publish-notifications {--batch-size=50}';
    protected $description = 'Publish mobile money transaction notifications to RabbitMQ';

    public function handle()
    {
        $this->info('Starting Mobile Money Transaction Notification Publisher...');

        try {
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );
            $channel = $connection->channel();

            $queueName = config('queue_settings.mobile_money_transaction_notification.publisher.queue_name');
            $channel->queue_declare($queueName, false, true, false, false);

            $batchSize = $this->option('batch-size');
            $this->info(sprintf('Using batch size: %d', $batchSize));

            while (true) {
                try {
                    $notifications = MobileMoneyTransactionNotification::unpublished()
                        ->limit($batchSize)
                        ->get();

                    if ($notifications->isEmpty()) {
                        $this->info('No pending notifications found. Waiting...');
                        sleep(1);
                        continue;
                    }

                    $this->info(sprintf('Found %d pending notifications', $notifications->count()));

                    $publishedIds = [];
                    foreach ($notifications as $notification) {
                        $message = new AMQPMessage(
                            json_encode($notification->toArray()),
                            ['delivery_mode' => 2]
                        );
                        $channel->basic_publish($message, '', $queueName);
                        $publishedIds[] = $notification->id;
                        
                        $this->info(sprintf('Published mobile money transaction notification - MSISDN: %s, Amount: %s, Reference: %s', 
                            $notification->msisdn, $notification->amount, $notification->reference));
                    }

                    if (!empty($publishedIds)) {
                        MobileMoneyTransactionNotification::whereIn('id', $publishedIds)
                            ->update(['publish_status' => true]);
                    }

                    $this->info(sprintf('Successfully published %d notifications', count($publishedIds)));
                } catch (\Exception $e) {
                    Log::error('Error publishing mobile money transaction notifications: ' . $e->getMessage());
                    $this->error('Error: ' . $e->getMessage());
                    sleep(3);
                }
            }

            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error('Error in mobile money transaction notification publisher: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }
} 