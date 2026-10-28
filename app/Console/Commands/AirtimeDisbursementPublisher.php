<?php

namespace App\Console\Commands;

use App\Models\WinnerAirtimeDisbursementNotification;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class AirtimeDisbursementPublisher extends Command
{
    protected $signature = 'airtime:disburse-publish';
    protected $description = 'Publish airtime disbursement notifications to queue';

    private $connection;
    private $channel;
    private $queueName;
    private $isConnected = false;

    public function __construct()
    {
        parent::__construct();
        $this->queueName = config('queue_settings.airtime_disbursement.publisher.queue_name');
    }

    public function handle()
    {
        $this->info('Starting airtime disbursement publisher...');

        while (true) {
            try {
                if (!$this->isConnected) {
                    $this->connect();
                }

                // Get pending notifications
                $notifications = WinnerAirtimeDisbursementNotification::where('publish_status', false)
                    ->orderBy('created_at')
                    ->limit(100)
                    ->get();

                foreach ($notifications as $notification) {
                    try {
                        $message = [
                            'msisdn' => $notification->msisdn,
                            'amount' => $notification->amount,
                            'box_type' => $notification->box_type,
                            'box_id' => $notification->box_id,
                            'winner_id' => $notification->winner_id,
                            'request_id' => $notification->request_id
                        ];

                        $msg = new AMQPMessage(
                            json_encode($message),
                            ['delivery_mode' => 2] // Make message persistent
                        );

                        $this->channel->basic_publish($msg, '', $this->queueName);

                        // Mark notification as published
                        $notification->update(['publish_status' => true]);

                        $this->info("Published airtime disbursement notification for MSISDN: {$notification->msisdn}");
                        Log::info('Published airtime disbursement notification', $message);
                    } catch (\Exception $e) {
                        Log::error('Error publishing notification: ' . $e->getMessage(), [
                            'notification_id' => $notification->id,
                            'msisdn' => $notification->msisdn
                        ]);
                        $this->error('Error publishing notification: ' . $e->getMessage());
                        
                        // If it's a connection error, break the inner loop to reconnect
                        if ($this->isConnectionError($e)) {
                            $this->isConnected = false;
                            break;
                        }
                    }
                }

                sleep(1);
            } catch (\Exception $e) {
                Log::error('Error in airtime disbursement publisher: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                
                $this->isConnected = false;
                sleep(1);
            }
        }
    }

    private function connect()
    {
        try {
            $this->connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );

            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->queueName, false, true, false, false);
            $this->isConnected = true;
            
            $this->info('Successfully connected to RabbitMQ');
        } catch (\Exception $e) {
            Log::error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            $this->error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            $this->isConnected = false;
            throw $e;
        }
    }

    private function isConnectionError(\Exception $e)
    {
        return str_contains($e->getMessage(), 'connection') || 
               str_contains($e->getMessage(), 'channel') ||
               str_contains($e->getMessage(), 'socket');
    }

    private function disconnect()
    {
        if ($this->channel) {
            try {
                $this->channel->close();
            } catch (\Exception $e) {
                Log::error('Error closing channel: ' . $e->getMessage());
            }
        }
        if ($this->connection) {
            try {
                $this->connection->close();
            } catch (\Exception $e) {
                Log::error('Error closing connection: ' . $e->getMessage());
            }
        }
        $this->isConnected = false;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
} 