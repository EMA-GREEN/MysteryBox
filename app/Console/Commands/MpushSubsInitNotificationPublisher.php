<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MpushSubsInitNotificationPublisher extends Command
{
    protected $signature = 'mpush:publish-subs-init';
    protected $description = 'Publish pending mpush subscription init notifications to RabbitMQ';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $batchSize;
    protected string $table = 'mpush_subs_init_notifications';

    public function __construct()
    {
        parent::__construct();
        $settings = config('queue_settings.mpush_subs_init_notification.publisher');
        $this->queueName = $settings['queue_name'];
        $this->batchSize = $settings['batch_size'];
    }

    protected function connect(): void
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

    public function handle(): void
    {
        while (true) {
            try {
                $this->connect();

                $notifications = DB::table($this->table)
                    ->where('IS_OUTGOING', 1)
                    ->orderBy('ID')
                    ->limit($this->batchSize)
                    ->get();

                if ($notifications->isEmpty()) {
                    sleep(1);
                    continue;
                }

                foreach ($notifications as $notification) {
                    $message = [
                        'msisdn' => (string) $notification->MSISDN,
                        'record_id' => $notification->ID,
                        'channel' => $notification->CHANNEL,
                        'ussd_data' => $notification->USSD_DATA,
                    ];

                    $msg = new AMQPMessage(
                        json_encode($message),
                        ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                    );

                    $this->channel->basic_publish($msg, '', $this->queueName);

                    DB::table($this->table)
                        ->where('ID', $notification->ID)
                        ->update(['IS_OUTGOING' => 0]);

                    Log::info('Published mpush subs init notification', $message);
                }
            } catch (\Exception $e) {
                Log::error('Error publishing mpush subs init notifications: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
            }

            sleep(1);
        }
    }
}
