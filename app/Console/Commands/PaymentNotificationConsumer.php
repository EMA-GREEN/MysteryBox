<?php

namespace App\Console\Commands;

use App\Services\YasPaymentService;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;

class PaymentNotificationConsumer extends Command
{
    protected $signature = 'payment:notify-consume {--consumer-id=1}';
    protected $description = 'Consume payment notifications from RabbitMQ queue';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $prefetchCount;

    public function __construct()
    {
        parent::__construct();
        $queueSettings = config('queue_settings.payment_notification.consumer');
        $this->queueName = $queueSettings['queue_name'];
        $this->prefetchCount = $queueSettings['prefetch_count'];
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
            $this->channel->basic_qos(null, $this->prefetchCount, null);
        }
    }

    public function handle()
    {
        $consumerId = $this->option('consumer-id');
        $this->info(sprintf('Starting Payment Notification Consumer #%d...', $consumerId));

        try {
            $this->connect();

            $callback = function ($msg) use ($consumerId) {
                try {
                    $data = json_decode($msg->body, true);
                    
                    $this->info(sprintf(
                        'Consumer #%d processing notification for MSISDN: %s',
                        $consumerId,
                        $data['msisdn']
                    ));
                    Log::info("Processing payment notification", $data);

                    // Validate required fields
                    if (!isset($data['msisdn']) || !isset($data['box_type']) || !isset($data['payment_type']) || !isset($data['request_id'])) {
                        throw new \Exception('Missing required fields: msisdn, box_type, payment_type, or request_id');
                    }

                    // Get amount from message or config
                    $amount = $data['amount'] ?? null;
                    
                    if ($amount === null) {
                        Log::error("Amount is missing in the message", $data);
                        throw new \Exception('Amount is missing in the message');
                    }

                    $requestId = $data['request_id'];
                    $service = new YasPaymentService();
                    
                    // Create payment record
                    $payment = Payment::create([
                        'msisdn' => $data['msisdn'],
                        'request_id' => $requestId,
                        'amount' => $amount,
                        'channel' => $data['channel'],
                        'status' => 'pending',
                        'box_type' => $data['box_type'],
                        'box_id' => $data['box_id'],
                        'payment_type' => $data['payment_type']
                    ]);

                    $response = $service->sendChargeRequest(
                        $requestId,
                        $data['msisdn'],
                        $amount
                    );

                    $response_data = $response['data'] ?? null;

                    Log::info("...Payment response:", [
                        'response' => $response_data
                    ]);

                    // Update payment transaction with acknowledgement response (including 429 etc)
                    $ackStatusCode = $response['data']['responseParam']['statusCode']
                        ?? $response['data']['status']
                        ?? $response['http_status']
                        ?? '500';
                    $ackDescription = $response['data']['responseParam']['description']
                        ?? $response['data']['message']
                        ?? $response['message']
                        ?? 'Unknown error';
                    $ackStatusFlag = $response['data']['responseParam']['status'] ?? null;

                    $updateData = [
                        'ack_status_code' => (string) $ackStatusCode,
                        'ack_description' => (string) $ackDescription,
                        'ack_received_time' => now(),
                        // If API explicitly says status=0, keep it pending; otherwise mark failed (e.g. 429)
                        'status' => $ackStatusFlag === '0' ? 'pending' : 'failed'
                    ];
                        
                        Log::info('Updating payment with acknowledgment data', [
                            'payment_id' => $payment->id,
                            'update_data' => $updateData,
                            'before_update_ack_time' => $payment->ack_received_time
                        ]);
                        
                        $updateResult = $payment->update($updateData);
                        Log::info('Update result', [
                            'payment_id' => $payment->id,
                            'update_result' => $updateResult,
                            'affected_rows' => $updateResult ? 1 : 0
                        ]);
                        
                        // Force refresh from database
                        $payment = Payment::find($payment->id);
                        
                        // Double-check the update worked
                        if (!$payment->ack_received_time) {
                            Log::error('Update failed - ack_received_time still null', [
                                'payment_id' => $payment->id,
                                'update_data' => $updateData
                            ]);
                            
                            // Try direct database update as fallback
                            $directUpdate = DB::table('payments')
                                ->where('id', $payment->id)
                                ->update(['ack_received_time' => now()]);
                            
                            Log::info('Direct database update fallback', [
                                'payment_id' => $payment->id,
                                'direct_update_result' => $directUpdate
                            ]);
                            
                            // Refresh again
                            $payment = Payment::find($payment->id);
                        }
                        
                        Log::info('Payment updated successfully', [
                            'payment_id' => $payment->id,
                            'ack_received_time' => $payment->ack_received_time,
                            'after_update_ack_time' => $payment->ack_received_time
                        ]);

                        $this->info(sprintf(
                            'Consumer #%d successfully sent payment request for MSISDN: %s',
                            $consumerId,
                            $data['msisdn']
                        ));
                        // Log::info("Payment request sent successfully", $response);

                        // Acknowledge successful message
                        $msg->ack();
                } catch (\Exception $e) {
                    // Update payment record with error details if it exists
                    if (isset($payment)) {
                        $updateResult = $payment->update([
                            'ack_status_code' => '500',
                            'ack_description' => $e->getMessage(),
                            'ack_received_time' => now(),
                            'status' => 'failed'
                        ]);
                        
                        // Force refresh from database
                        $payment = Payment::find($payment->id);
                        
                        // Double-check the update worked
                        if (!$payment->ack_received_time) {
                            Log::error('Exception update failed - ack_received_time still null', [
                                'payment_id' => $payment->id
                            ]);
                            
                            // Try direct database update as fallback
                            $directUpdate = DB::table('payments')
                                ->where('id', $payment->id)
                                ->update(['ack_received_time' => now()]);
                            
                            Log::info('Direct database update fallback (exception case)', [
                                'payment_id' => $payment->id,
                                'direct_update_result' => $directUpdate
                            ]);
                            
                            // Refresh again
                            $payment = Payment::find($payment->id);
                        }
                    }

                    $this->error(sprintf(
                        'Consumer #%d error processing message: %s',
                        $consumerId,
                        $e->getMessage()
                    ));
                    Log::error("Error processing payment notification: " . $e->getMessage());

                    // Reject message on unexpected exception
                    if (method_exists($msg, 'reject')) {
                        $msg->reject(false);
                    }
                }
            };

            $this->channel->basic_consume($this->queueName, '', false, false, false, false, $callback);

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            $this->error(sprintf(
                'Consumer #%d error: %s',
                $consumerId,
                $e->getMessage()
            ));
            Log::error("Error in payment notification consumer: " . $e->getMessage());
        } finally {
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->close();
            }
            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        }
    }
}