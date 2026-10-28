<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class AppNotificationPublisher extends Command
{
    protected $signature = 'app:publish-notifications';
    protected $description = 'Publish app notifications to RabbitMQ queue';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $batchSize;

    public function __construct()
    {
        parent::__construct();
        $queueSettings = config('queue_settings.app_notification.publisher');
        $this->queueName = $queueSettings['queue_name'];
        $this->batchSize = $queueSettings['batch_size'];
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
        }
    }

    public function handle()
    {
        while (true) {
            try {
                $this->connect();

                $notifications = AppNotification::where('publish_status', false)
                    ->limit($this->batchSize)
                    ->get();

                if ($notifications->isEmpty()) {
                    $this->info('No pending notifications to publish.');
                    sleep(1); // Wait before checking again
                    continue;
                }

                $publishedIds = [];
                foreach ($notifications as $notification) {
                    $message = [
                        'msisdn' => $notification->msisdn,
                        'action' => $notification->action,
                        'notif_id' => $notification->id,
                        'box_type' => $notification->box_type,
                        'box_id' => $notification->box_id
                    ];

                    $msg = new AMQPMessage(
                        json_encode($message),
                        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                    );

                    $this->channel->basic_publish($msg, '', $this->queueName);
                    $publishedIds[] = $notification->id;

                    $this->info("Published notification for MSISDN: {$notification->msisdn}");
                    Log::info("Published app notification", $message);
                }

                // Bulk update all published notifications
                if (!empty($publishedIds)) {
                    AppNotification::whereIn('id', $publishedIds)
                        ->update(['publish_status' => true]);
                }

                $this->info('Successfully published ' . count($publishedIds) . ' notifications.');
            } catch (\Exception $e) {
                Log::error('Error publishing app notifications: ' . $e->getMessage());
                $this->error('Error publishing notifications: ' . $e->getMessage());
            }
            sleep(1); // Wait before next batch
        }
    }
} 