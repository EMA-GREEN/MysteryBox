<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Support\DefaultRedisPredis;
use Predis\Client as RedisClient;

class RenewalPaymentLowPriorityPublisher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mysterybox:renewal:payment-low-priority-publish';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Publishes low priority renewal payment messages to RabbitMQ queue';

    private $connection;
    private $channel;
    private $queueSettings;
    private $maxRetries;
    private $retryTrackingFile;
    private $lastDateFile;
    private $redis;
    private $maxPublishTPS;

    public function __construct()
    {
        parent::__construct();
        $this->queueSettings = config('queue_settings');
        $this->maxRetries = config('renewal.processing.low_priority_max_retries');
        $this->retryTrackingFile = config('renewal.tracking_files.low_priority_publisher.retry_tracking');
        $this->lastDateFile = config('renewal.tracking_files.low_priority_publisher.last_date');
        $this->maxPublishTPS = config('renewal.tps_limits.low_priority_publisher', 150);
        $this->redis = DefaultRedisPredis::client();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Renewal Payment Low Priority Publisher...');
        
        while (true) {
            try {
                // Check if we're in the reset time window (00:00 to 00:11)
                // Renewal reset runs at 00:01, give it up to 10 minutes to finish
                if ($this->isResetTimeWindow()) {
                    $this->info('RenewalReset time window (00:00-00:11), skipping processing...');
                    sleep(1);
                    continue;
                }
                
                // Initialize RabbitMQ connection if not connected
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->connect();
                }
                
                $config = $this->queueSettings['renewal_payment_low_priority'];
                $queueName = $config['publisher']['queue_name'];
                $queueInfo = $this->channel->queue_declare($queueName, false, true, false, false);
                $messageCount = $queueInfo[1];
                if ($messageCount >= 1000) {
                    sleep(1);
                    continue;
                }
                
                // Get current retry level
                $currentRetryLevel = $this->getLastProcessedRetry();
                $this->info("Processing retry level: $currentRetryLevel");
                
                // Process current retry level
                $this->processRetryLevel($currentRetryLevel);
                
                // Check if we should advance to next retry level
                $this->checkAndAdvanceRetry($currentRetryLevel);
                
            } catch (\Exception $e) {
                Log::error('Error in Renewal Payment Low Priority Publisher: ' . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                
                // Close connection on error to force reconnection
                if ($this->connection && $this->connection->isConnected()) {
                    try {
                        $this->channel->close();
                        $this->connection->close();
                    } catch (\Exception $closeException) {
                        // Ignore close errors
                    }
                }
            }
            
            // Sleep before next iteration
            sleep(1);
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
        $this->channel->queue_declare($config['publisher']['queue_name'], false, true, false, false);
        $this->channel->queue_bind($config['publisher']['queue_name'], $config['exchange'], $config['routing_key']);
        
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

    /**
     * TPS control for publishing: returns true if a message can be published within rate limit.
     */
    private function canPublishMessage(): bool
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

        $result = $this->redis->eval($luaScript, 1, 'renewal_low_priority_publisher_token_bucket', $this->maxPublishTPS);
        return $result == 1;
    }

    private function processRetryLevel($retryLevel)
    {
        $config = $this->queueSettings['renewal_payment_low_priority'];
        $batchSize = $config['publisher']['batch_size'];
        
        $totalProcessed = 0;
        
        do {
            // Select records for current retry level with priority=2
            // Order by most recently billed MSISDNs first (most billable/chargeable)
            // Use < to prevent retry_count from exceeding maxRetries after increment
            $sql = "SELECT id, msisdn, retry_status, retry_count 
                    FROM renewal_subscribers 
                    WHERE activity_status = 'ACTIVE' 
                    AND dnd_status = 'OFF' 
                    AND retry_count < ? 
                    AND publish_status = 0 
                    AND priority = 2 
                    ORDER BY last_renewal_billing DESC, retry_count ASC 
                    LIMIT ?";
            
            // $records = DB::select($sql, [$this->maxRetries, $batchSize]);
            $records = DB::select($sql, [$retryLevel, $batchSize]);
                
            if (empty($records)) {
                break;
            }
            
            $ids = array_column($records, 'id');
            $idsString = implode(',', $ids);
            
            // Update records using the specific update criteria provided
            $updateSql = "UPDATE renewal_subscribers 
                         SET publish_status = 1, retry_count = retry_count + 1 
                         WHERE id IN ($idsString)";
            
            DB::statement($updateSql);
            
            // Publish messages to queue (TPS limited)
            foreach ($records as $record) {
                while (!$this->canPublishMessage()) {
                    usleep(100000); // 100ms
                }
                $message = [
                    'id' => $record->id,
                    'msisdn' => $record->msisdn,
                    'retry_count' => $record->retry_count + 1,
                    'priority' => 'low',
                    'published_at' => now()->toISOString()
                ];
                
                $amqpMessage = new AMQPMessage(
                    json_encode($message),
                    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
                );
                
                $this->channel->basic_publish(
                    $amqpMessage,
                    $config['exchange'],
                    $config['routing_key']
                );
            }
            
            $totalProcessed += count($records);
            $this->info("Published " . count($records) . " low priority messages for retry level $retryLevel. Total: $totalProcessed");
            
        } while (count($records) == $batchSize);
        
        $this->info("Completed retry level $retryLevel. Total processed: $totalProcessed");
    }

    private function getLastProcessedRetry()
    {
        if (!file_exists($this->retryTrackingFile)) {
            return 0;
        }
        
        clearstatcache(true, $this->retryTrackingFile);
        $data = json_decode(file_get_contents($this->retryTrackingFile), true);
        return $data['current_retry'] ?? 0;
    }

    private function checkAndAdvanceRetry($currentRetry)
    {
        $remainingCount = $this->getRemainingCountAtLevel($currentRetry, 2);
        
        if ($remainingCount == 0) {
            // --- COOLDOWN FEATURE (rollback: delete this block, keep only the $nextRetry block below) ---
            // Prevents advancing before callbacks arrive. Publisher can finish before consumers;
            // callbacks arrive 1-2 sec per request. Config: advancement_cooldown_seconds, advancement_check_interval
            $cooldownSeconds = config('renewal.processing.advancement_cooldown_seconds', 300);
            $checkInterval = config('renewal.processing.advancement_check_interval', 30);
            $elapsed = 0;

            while ($elapsed < $cooldownSeconds) {
                sleep($checkInterval);
                $elapsed += $checkInterval;

                $remainingCount = $this->getRemainingCountAtLevel($currentRetry, 2);
                $threshold = config('renewal.processing.advancement_pending_threshold', 20);
                if ($remainingCount > $threshold) {
                    Log::info("Found $remainingCount pending after cooldown check (callbacks arrived), not advancing");
                    return;
                }
                // remainingCount <= threshold: likely new MSISDNs trickling in, continue cooldown
            }
            // --- END COOLDOWN FEATURE ---

            $nextRetry = $currentRetry + 1;
            
            if ($nextRetry <= $this->maxRetries) {
                $this->saveCurrentRetry($nextRetry);
                $this->info("Advanced to retry level: $nextRetry");
            } else {
                $this->resetForNextDay();
                $this->info("All retry levels completed. Reset for next day.");
            }
        }
    }

    /**
     * Get count of records pending at retry level (used by checkAndAdvanceRetry).
     * COOLDOWN ROLLBACK: if reverting cooldown, you can inline this SQL back into checkAndAdvanceRetry.
     */
    private function getRemainingCountAtLevel($retryLevel, $priority)
    {
        $sql = "SELECT COUNT(*) as count 
                FROM renewal_subscribers 
                WHERE activity_status = 'ACTIVE' 
                AND dnd_status = 'OFF' 
                AND retry_count < ? 
                AND publish_status = 0 
                AND priority = ?";
        
        $result = DB::select($sql, [$retryLevel, $priority]);
        return (int) $result[0]->count;
    }

    private function saveCurrentRetry($retry)
    {
        $data = [
            'current_retry' => $retry,
            'updated_at' => now()->toISOString()
        ];
        
        file_put_contents($this->retryTrackingFile, json_encode($data));
    }

    private function resetForNextDay()
    {
        $today = now()->format('Y-m-d');
        
        // Check if we need to reset for a new day
        $lastDate = $this->getLastProcessedDate();
        
        if ($lastDate !== $today) {
            $this->saveCurrentRetry(0);
            $this->saveLastProcessedDate($today);
            $this->info("Reset for new day: $today");
        }
    }

    private function getLastProcessedDate()
    {
        if (!file_exists($this->lastDateFile)) {
            return null;
        }
        
        clearstatcache(true, $this->lastDateFile);
        $data = json_decode(file_get_contents($this->lastDateFile), true);
        return $data['last_date'] ?? null;
    }

    private function saveLastProcessedDate($date)
    {
        $data = [
            'last_date' => $date,
            'updated_at' => now()->toISOString()
        ];
        
        file_put_contents($this->lastDateFile, json_encode($data));
    }

    /**
     * Check if current time is in the reset time window (00:00 to 00:11)
     * Renewal reset runs at 00:01 via cronjob, needs up to 10 minutes to finish
     * (Allowing buffer for 700k+ records UPDATE operation)
     * 
     * @return bool
     */
    private function isResetTimeWindow()
    {
        $now = \Carbon\Carbon::now();
        $hour = $now->hour;
        $minute = $now->minute;
        
        // Check if time is between 00:00 and 00:11 (11 minute window for safety)
        if ($hour == 0 && $minute >= 0 && $minute < 11) {
            return true;
        }
        
        return false;
    }
}