<?php

namespace App\Console\Commands;

use App\Models\RenewalPaymentCallback;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class RenewalPaymentCallbackPublisher extends Command
{
    protected $signature = 'renewal:payment:callback:publish';
    protected $description = 'Publish renewal payment callbacks to RabbitMQ queue';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $batchSize;

    public function __construct()
    {
        parent::__construct();
        $queueSettings = config('queue_settings.renewal_payment_callback.publisher');
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

                $callbacks = RenewalPaymentCallback::where('publish_status', false)
                    ->orderBy('created_at', 'asc')
                    ->limit($this->batchSize)
                    ->get();

                if ($callbacks->isEmpty()) {
                    $this->info('No pending renewal payment callbacks to publish.');
                    sleep(1); // Wait before checking again
                    continue;
                }

                $publishedIds = [];
                foreach ($callbacks as $callback) {
                    $message = [
                        'msisdn' => $callback->msisdn,
                        'request_id' => $callback->request_id,
                        'amount' => $callback->amount,
                        'status' => $callback->status,
                        'details' => $callback->details,
                        'channel' => $callback->channel,
                        'billing_system' => $callback->billing_system
                    ];

                    $msg = new AMQPMessage(
                        json_encode($message),
                        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                    );

                    $this->channel->basic_publish($msg, '', $this->queueName);
                    $publishedIds[] = $callback->id;

                    $this->info("Published renewal payment callback for MSISDN: {$callback->msisdn}");
                    Log::info("Published renewal payment callback", $message);
                }

                // Bulk update all published callbacks
                if (!empty($publishedIds)) {
                    RenewalPaymentCallback::whereIn('id', $publishedIds)
                        ->update(['publish_status' => true]);
                }

                $this->info('Successfully published ' . count($publishedIds) . ' renewal payment callbacks.');
            } catch (\Exception $e) {
                Log::error('Error publishing renewal payment callbacks: ' . $e->getMessage());
                $this->error('Error publishing renewal payment callbacks: ' . $e->getMessage());
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