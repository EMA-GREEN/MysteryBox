<?php

namespace App\Console\Commands;

use App\Models\WinnerWalletDisbursementNotification;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class WalletDisbursementPublisher extends Command
{
    protected $signature = 'wallet:disburse-publish';
    protected $description = 'Publish wallet disbursement notifications to queue';

    private $connection;
    private $channel;
    private $queueName;
    private $batchSize;
    private $lockName;

    public function __construct()
    {
        parent::__construct();
        $this->queueName = config('queue_settings.wallet_disbursement.publisher.queue_name');
        $this->batchSize = config('queue_settings.wallet_disbursement.publisher.batch_size');
        $this->lockName = config('queue_settings.wallet_disbursement.publisher.lock_name');
    }

    public function handle()
    {
        $this->info('Starting wallet disbursement publisher...');

        while (true) {
            try {
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->connect();
                }

                $this->publishNotifications();
                sleep(1);
            } catch (\Exception $e) {
                Log::error('Error in wallet disbursement publisher: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                
                if ($this->connection) {
                    $this->disconnect();
                }
                
                sleep(1);
            }
        }
    }

    private function connect()
    {
        $this->connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password')
        );

        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($this->queueName, false, true, false, false);
    }

    private function publishNotifications()
    {
        $notifications = WinnerWalletDisbursementNotification::where('publish_status', false)
            ->limit($this->batchSize)
            ->get();

        foreach ($notifications as $notification) {
            $message = new AMQPMessage(
                json_encode([
                    'msisdn' => $notification->msisdn,
                    'amount' => $notification->amount,
                    'box_type' => $notification->box_type,
                    'box_id' => $notification->box_id,
                    'winner_id' => $notification->winner_id,
                    'request_id' => $notification->request_id
                ]),
                ['delivery_mode' => 2]
            );

            $this->channel->basic_publish($message, '', $this->queueName);
            $notification->update(['publish_status' => true]);
            
            $this->info("Published notification for MSISDN: {$notification->msisdn}");
        }
    }

    private function disconnect()
    {
        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->connection) {
            $this->connection->close();
        }
    }
} 