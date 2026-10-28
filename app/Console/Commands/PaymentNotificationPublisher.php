<?php

namespace App\Console\Commands;

use App\Models\PaymentNotification;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class PaymentNotificationPublisher extends Command
{
    protected $signature = 'payment:notify-publish';
    protected $description = 'Publish payment notifications to RabbitMQ queue';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $batchSize;

    public function __construct()
    {
        parent::__construct();
        $queueSettings = config('queue_settings.payment_notification.publisher');
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

                $notifications = PaymentNotification::where('publish_status', false)
                    ->orderBy('created_at', 'asc')
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
                        'box_type' => $notification->box_type,
                        'amount' => $notification->amount,
                        'payment_type' => $notification->payment_type,
                        'request_id' => $notification->request_id,
                        'box_id' => $notification->box_id,
                        'channel' => $notification->channel
                    ];

                    $msg = new AMQPMessage(
                        json_encode($message),
                        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                    );

                    $this->channel->basic_publish($msg, '', $this->queueName);
                    $publishedIds[] = $notification->id;

                    $this->info("Published notification for MSISDN: {$notification->msisdn}");
                    Log::info("Published payment notification", $message);
                }

                // Bulk update all published notifications
                if (!empty($publishedIds)) {
                    PaymentNotification::whereIn('id', $publishedIds)
                        ->update(['publish_status' => true]);
                }

                $this->info('Successfully published ' . count($publishedIds) . ' notifications.');
            } catch (\Exception $e) {
                Log::error('Error publishing payment notifications: ' . $e->getMessage());
                $this->error('Error publishing notifications: ' . $e->getMessage());
            } finally {
                if ($this->channel && $this->channel->is_open()) {
                    $this->channel->close();
                }
                if ($this->connection && $this->connection->isConnected()) {
                    $this->connection->close();
                }
            }
            sleep(1); // Wait before next batch
        }
    }
} 