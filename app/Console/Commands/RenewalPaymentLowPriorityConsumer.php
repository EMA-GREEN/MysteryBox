<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use App\Services\YasPaymentService;
use App\Services\GlobalDndChecker;
use App\Models\RenewalPayment;
use App\Support\DefaultRedisPredis;
use Predis\Client as RedisClient;

class RenewalPaymentLowPriorityConsumer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mysterybox:renewal:payment-low-priority-consume';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Consumes low priority renewal payment messages from RabbitMQ queue';

    private $connection;
    private $channel;
    private $queueSettings;
    private $yasPaymentService;
    private $globalDndChecker;
    private $redis;
    private $maxTPS; // Configurable TPS limit from renewal config

    public function __construct(YasPaymentService $yasPaymentService, GlobalDndChecker $globalDndChecker)
    {
        parent::__construct();
        $this->yasPaymentService = $yasPaymentService;
        $this->globalDndChecker = $globalDndChecker;
        $this->queueSettings = config('queue_settings');
        $this->maxTPS = config('renewal.tps_limits.low_priority_queue', 100);
        $this->redis = DefaultRedisPredis::client();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $this->info('Starting Renewal Payment Low Priority Consumer...');
            
            // Initialize RabbitMQ connection
            $this->connect();
            
            // Set up consumer
            $this->setupConsumer();
            
            $this->info('Waiting for messages. To exit press CTRL+C');
            
            // Start consuming
            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
            
            return 0;
        } catch (\Exception $e) {
            Log::error('RenewalPaymentLowPriorityConsumer error: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            return 1;
        } finally {
            $this->closeConnection();
        }
    }

    private function connect()
    {
        $config = $this->queueSettings['renewal_payment_low_priority'];
        
        $this->connection = new AMQPStreamConnection(
            config('rabbitmq.host'),
            config('rabbitmq.port'),
            config('rabbitmq.user'),
            config('rabbitmq.password')
        );
        
        $this->channel = $this->connection->channel();
        
        // Declare exchange and queue
        $this->channel->exchange_declare($config['exchange'], 'direct', false, true, false);
        $this->channel->queue_declare($config['consumer']['queue_name'], false, true, false, false);
        $this->channel->queue_bind($config['consumer']['queue_name'], $config['exchange'], $config['routing_key']);
        
        $this->info('Connected to RabbitMQ successfully.');
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

    private function setupConsumer()
    {
        $config = $this->queueSettings['renewal_payment_low_priority'];
        
        // Set QoS
        $this->channel->basic_qos(null, $config['consumer']['prefetch_count'], null);
        
        // Set up callback
        $callback = function ($msg) {
            $this->processMessage($msg);
        };
        
        // Start consuming
        $this->channel->basic_consume(
            $config['consumer']['queue_name'],
            '',
            false,
            false,
            false,
            false,
            $callback
        );
    }

    private function processMessage($msg)
    {
        try {
            // TPS control - wait if necessary
            while (!$this->canProcessMessage()) {
                $this->info("TPS limit reached, waiting...");
                usleep(100000); // Wait 100ms
            }
            
            $messageBody = $msg->getBody();
            $message = json_decode($messageBody, true);
            
            if (!$message) {
                Log::error('Invalid message format: ' . $messageBody);
                $msg->ack();
                return;
            }
            
            // $this->info("Processing message for MSISDN: {$message['msisdn']}");
            
            // Create renewal payment record
            $renewalPayment = $this->createRenewalPayment($message);
            
            if (!$renewalPayment) {
                Log::error('Failed to create renewal payment record', $message);
                $msg->nack(false, true); // Requeue message
                return;
            }
            
            // Send charging request
            $chargeResult = $this->sendChargingRequest($message, $renewalPayment);
            
            if ($chargeResult['success']) {
                // Update renewal payment with acknowledgement details
                $this->updateRenewalPaymentAck($renewalPayment, $chargeResult['response']);
                
                // $this->info("Successfully processed charging request for MSISDN: {$message['msisdn']}");
                $msg->ack();
            } else {
                Log::error('Charging request failed', [
                    'msisdn' => $message['msisdn'],
                    'error' => $chargeResult['error']
                ]);
                
                // Update renewal payment with error details
                $this->updateRenewalPaymentError($renewalPayment, $chargeResult['error']);
                
                $msg->ack(); // Don't requeue failed charges
            }
            
        } catch (\Exception $e) {
            Log::error('Error processing message: ' . $e->getMessage(), [
                'message' => $messageBody ?? 'Unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            $msg->nack(false, true); // Requeue on unexpected errors
        }
    }

    private function canProcessMessage()
    {
        $luaScript = <<<LUA
        redis.replicate_commands()
        local key = KEYS[1]
        local maxTokens = tonumber(ARGV[1])
        local refillRate = maxTokens / 1

        local currentTime = redis.call('TIME')
        local now = tonumber(currentTime[1]) + tonumber(currentTime[2]) * 1e-6

        local bucket = redis.call('HMGET', key, 'tokens', 'last_refill')
        local tokens = tonumber(bucket[1]) or maxTokens
        local lastRefill = tonumber(bucket[2]) or now

        local elapsed = now - lastRefill
        local newTokens = math.min(maxTokens, tokens + elapsed * refillRate)

        if newTokens >= 1 then
            redis.call('HMSET', key, 'tokens', newTokens - 1, 'last_refill', now)
            redis.call('EXPIRE', key, 1)
            return 1
        else
            return 0
        end
        LUA;

        $result = $this->redis->eval($luaScript, 1, "renewal_low_priority_token_bucket", $this->maxTPS);
        return $result == 1;
    }

    private function createRenewalPayment($message)
    {
        try {
            $date = now()->format('YmdHis');
            $requestId = "MYBXR" . $date . $message['msisdn'];
            
            $renewalPayment = RenewalPayment::create([
                'msisdn' => $message['msisdn'],
                'request_id' => $requestId,
                'payment_type' => 'renewal', // Set appropriate payment type
                'amount' => config('renewal.default_amount', 10), // Default amount from renewal config
                'status' => 'pending', // Use lowercase to match model constants
            ]);
            
            return $renewalPayment;
            
        } catch (\Exception $e) {
            Log::error('Error creating renewal payment: ' . $e->getMessage(), $message);
            return null;
        }
    }

    private function sendChargingRequest($message, $renewalPayment)
    {
        try {
            $requestId = $renewalPayment->request_id;
            $msisdn = $message['msisdn'];
            $amount = $renewalPayment->amount;
            
            // $this->info("Sending charge request - RequestID: $requestId, MSISDN: $msisdn, Amount: $amount");
            
            // Get renewal_subscribers by msisdn and validate before charging
            $renewalSubscriber = DB::table('renewal_subscribers')
                ->where('msisdn', $msisdn)
                ->select('activity_status', 'dnd_status', 'last_renewal_billing')
                ->first();
            
            if (!$renewalSubscriber) {
                return [
                    'success' => false,
                    'error' => 'Renewal subscriber not found'
                ];
            }
            
            // Check if activity_status is ACTIVE
            if ($renewalSubscriber->activity_status !== 'ACTIVE') {
                // $this->info("Skipping charge request - Subscriber not active: $msisdn");
                return [
                    'success' => false,
                    'error' => 'Subscriber activity_status is not ACTIVE'
                ];
            }
            
            // Check if dnd_status is OFF
            if ($renewalSubscriber->dnd_status !== 'OFF') {
                // $this->info("Skipping charge request - DND is ON: $msisdn");
                return [
                    'success' => false,
                    'error' => 'Subscriber dnd_status is not OFF'
                ];
            }

            // Yas global DND Redis set (skip when YAS_DND_REDIS_CHECK_ENABLED=false)
            $dndCheck = $this->globalDndChecker->check($msisdn);
            if ($dndCheck['result'] !== GlobalDndChecker::RESULT_ALLOWED) {
                $this->info('Skipping charge request - ' . $dndCheck['reason'] . ": $msisdn");
                return [
                    'success' => false,
                    'error' => $dndCheck['reason'],
                ];
            }
            
            // Check if last_renewal_billing is not today (to avoid double billing)
            $today = now()->format('Y-m-d');
            if ($renewalSubscriber->last_renewal_billing === $today) {
                $this->info("Skipping charge request - Already billed today: $msisdn");
                return [
                    'success' => false,
                    'error' => 'Subscriber already billed today'
                ];
            }
            
            // Send charging request using YasPaymentService
            $response = $this->yasPaymentService->sendChargeRequest($requestId, $msisdn, $amount);
            
            if ($response && isset($response['status'], $response['data'])) {
                return [
                    'success' => true,
                    'response' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Invalid response from payment service'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function updateRenewalPaymentAck($renewalPayment, $response)
    {
        try {
            $ackStatusCode = $response['data']['responseParam']['statusCode']
                ?? $response['data']['status']
                ?? $response['http_status']
                ?? '500';
            $ackDescription = $response['data']['responseParam']['description']
                ?? $response['data']['message']
                ?? $response['message']
                ?? 'Unknown error';
            $ackStatusFlag = $response['data']['responseParam']['status'] ?? null;

            $renewalPayment->update([
                'ack_status_code' => (string) $ackStatusCode,
                'ack_description' => (string) $ackDescription,
                'ack_received_time' => now(),
                // If API explicitly says status=0, keep it pending; otherwise mark failed (e.g. 429)
                'status' => $ackStatusFlag === '0' ? 'pending' : 'failed'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating renewal payment acknowledgement: ' . $e->getMessage(), [
                'renewal_payment_id' => $renewalPayment->id
            ]);
        }
    }

    private function updateRenewalPaymentError($renewalPayment, $error)
    {
        try {
            $renewalPayment->update([
                'status' => 'failed', // Use lowercase to match model constants
                'ack_description' => $error, // Store error in ack_description field
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating renewal payment error: ' . $e->getMessage(), [
                'renewal_payment_id' => $renewalPayment->id
            ]);
        }
    }
}