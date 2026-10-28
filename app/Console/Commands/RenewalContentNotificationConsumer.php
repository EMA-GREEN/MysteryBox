<?php

namespace App\Console\Commands;

use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RenewalContentNotificationConsumer extends Command
{
    protected $signature = 'renewal:content-notification-consumer';
    protected $description = 'Consume renewal content notifications from RabbitMQ queue and send SMS';

    private $connection;
    private $channel;
    private $queueName;
    private $prefetchCount;
    private $smsService;

    public function __construct()
    {
        parent::__construct();
        $this->smsService = new SmsService();
    }

    public function handle()
    {
        try {
            $this->initializeConnection();
            $this->info('Starting renewal content notification consumer...');
            
            // Set prefetch count for fair distribution
            $this->channel->basic_qos(null, $this->prefetchCount, null);
            
            // Set up consumer
            $this->channel->basic_consume(
                $this->queueName,
                '',
                false,
                false,
                false,
                false,
                [$this, 'processNotification']
            );

            $this->info('Waiting for renewal content notifications. To exit press CTRL+C');

            // Keep consuming messages
            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }

        } catch (\Exception $e) {
            $this->error('Error in renewal content notification consumer: ' . $e->getMessage());
            Log::error('RenewalContentNotificationConsumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            $this->closeConnection();
        }
    }

    public function processNotification(AMQPMessage $message)
    {
        try {
            $data = json_decode($message->getBody(), true);
            
            if (!$data) {
                $this->error('Invalid JSON in message: ' . $message->getBody());
                $message->ack(false, false);
                return;
            }

            $this->info(sprintf(
                'Processing renewal content notification - MSISDN: %s, Type: %s',
                $data['msisdn'] ?? 'N/A',
                $data['payment_type'] ?? 'N/A'
            ));

            $msisdn = $data['msisdn'] ?? null;
            $text = $data['text'] ?? null;

            if (!$msisdn || !$text) {
                $this->error('Missing required fields: msisdn or text');
                $message->ack(); // Acknowledge to remove from queue
                return;
            }

            // Send SMS to the msisdn
            try {
                $this->smsService->sendSms($msisdn, $text);
                
                $this->info(sprintf(
                    'Successfully sent renewal SMS to MSISDN: %s',
                    $msisdn
                ));

                Log::info('Renewal content notification SMS sent', [
                    'msisdn' => $msisdn,
                    'payment_type' => $data['payment_type'] ?? 'N/A',
                    'request_id' => $data['request_id'] ?? 'N/A',
                    'text_length' => strlen($text)
                ]);

            } catch (\Exception $e) {
                $this->error(sprintf(
                    'Failed to send SMS to MSISDN: %s, Error: %s',
                    $msisdn,
                    $e->getMessage()
                ));

                Log::error('Failed to send renewal content notification SMS', [
                    'msisdn' => $msisdn,
                    'payment_type' => $data['payment_type'] ?? 'N/A',
                    'request_id' => $data['request_id'] ?? 'N/A',
                    'error' => $e->getMessage(),
                    'text' => $text
                ]);

                // Still acknowledge the message to prevent infinite retries
                // You might want to implement a dead letter queue for failed SMS
            }

            $message->ack();

        } catch (\Exception $e) {
            $this->error('Error processing renewal content notification: ' . $e->getMessage());
            Log::error('RenewalContentNotificationConsumer processing error', [
                'message_body' => $message->getBody(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $message->ack(false, false);
        }
    }

    private function initializeConnection()
    {
        $queueSettings = config('queue_settings.renewal_content_notification.consumer');
        
        $this->queueName = $queueSettings['queue_name'];
        $this->prefetchCount = $queueSettings['prefetch_count'];

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