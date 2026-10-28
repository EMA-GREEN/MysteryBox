<?php

namespace App\Console\Commands;

use App\Models\AirtimeDisbursementCallback;
use App\Models\WalletDisbursementCallback;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;

class DisbursementCallbackPublisher extends Command
{
    protected $signature = 'disbursement:callback:publish {--type=all : Type of callback to publish (airtime, wallet, or all)}';
    protected $description = 'Publish pending disbursement callbacks to queue';

    protected $connection;
    protected $channel;
    protected $airtimeQueueName;
    protected $walletQueueName;
    protected $airtimeBatchSize;
    protected $walletBatchSize;

    public function __construct()
    {
        parent::__construct();
        $airtimeSettings = config('queue_settings.airtime_disbursement_callback.publisher');
        $walletSettings = config('queue_settings.wallet_disbursement_callback.publisher');
        
        $this->airtimeQueueName = $airtimeSettings['queue_name'];
        $this->walletQueueName = $walletSettings['queue_name'];
        $this->airtimeBatchSize = $airtimeSettings['batch_size'];
        $this->walletBatchSize = $walletSettings['batch_size'];
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
        }
    }

    public function handle()
    {
        $type = $this->option('type');
        $this->info("Starting Disbursement Callback Publisher for type: {$type}");

        while (true) {
            try {
                $this->connect();

                $publishedCount = 0;

                if ($type === 'all' || $type === 'airtime') {
                    $publishedCount += $this->publishAirtimeCallbacks();
                }

                if ($type === 'all' || $type === 'wallet') {
                    $publishedCount += $this->publishWalletCallbacks();
                }

                if ($publishedCount === 0) {
                    // $this->info('No pending callbacks to publish.');
                } else {
                    $this->info("Successfully published {$publishedCount} callbacks.");
                }

            } catch (\Exception $e) {
                $this->error('Error in disbursement callback publisher: ' . $e->getMessage());
                Log::error('Error in disbursement callback publisher: ' . $e->getMessage());
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

    protected function publishAirtimeCallbacks()
    {
        $this->channel->queue_declare($this->airtimeQueueName, false, true, false, false);

        $pendingCallbacks = AirtimeDisbursementCallback::where('publish_status', false)
            ->orderBy('created_at', 'asc')
            ->limit($this->airtimeBatchSize)
            ->get();

        if ($pendingCallbacks->isEmpty()) {
            return 0;
        }

        $this->info("Found {$pendingCallbacks->count()} pending airtime disbursement callbacks");

        $publishedIds = [];
        foreach ($pendingCallbacks as $callback) {
            $message = [
                'callback_id' => $callback->id,
                'reference_id' => $callback->reference_id,
                'msisdn' => $callback->msisdn,
                'amount' => $callback->amount,
                'status' => $callback->status,
                'message' => $callback->message,
                'transaction_id' => $callback->transaction_id,
                'balance' => $callback->balance,
                'callback_source' => $callback->callback_source,
                'callback_received_at' => $callback->callback_received_at,
                'type' => 'airtime'
            ];

            $this->channel->basic_publish(
                new \PhpAmqpLib\Message\AMQPMessage(json_encode($message)),
                '',
                $this->airtimeQueueName
            );

            $publishedIds[] = $callback->id;
            $this->info("Published airtime callback ID: {$callback->id}");
            Log::info("Published airtime disbursement callback", ['callback_id' => $callback->id]);
        }

        // Bulk update all published callbacks
        if (!empty($publishedIds)) {
            AirtimeDisbursementCallback::whereIn('id', $publishedIds)
                ->update(['publish_status' => true]);
        }

        return count($publishedIds);
    }

    protected function publishWalletCallbacks()
    {
        $this->channel->queue_declare($this->walletQueueName, false, true, false, false);

        $pendingCallbacks = WalletDisbursementCallback::where('publish_status', false)
            ->orderBy('created_at', 'asc')
            ->limit($this->walletBatchSize)
            ->get();

        if ($pendingCallbacks->isEmpty()) {
            return 0;
        }

        $this->info("Found {$pendingCallbacks->count()} pending wallet disbursement callbacks");

        $publishedIds = [];
        foreach ($pendingCallbacks as $callback) {
            $message = [
                'callback_id' => $callback->id,
                'reference_id' => $callback->reference_id,
                'msisdn' => $callback->msisdn,
                'amount' => $callback->amount,
                'status' => $callback->status,
                'message' => $callback->message,
                'transaction_id' => $callback->transaction_id,
                'balance' => $callback->balance,
                'callback_source' => $callback->callback_source,
                'callback_received_at' => $callback->callback_received_at,
                'type' => 'wallet'
            ];

            $this->channel->basic_publish(
                new \PhpAmqpLib\Message\AMQPMessage(json_encode($message)),
                '',
                $this->walletQueueName
            );

            $publishedIds[] = $callback->id;
            $this->info("Published wallet callback ID: {$callback->id}");
            Log::info("Published wallet disbursement callback", ['callback_id' => $callback->id]);
        }

        // Bulk update all published callbacks
        if (!empty($publishedIds)) {
            WalletDisbursementCallback::whereIn('id', $publishedIds)
                ->update(['publish_status' => true]);
        }

        return count($publishedIds);
    }
}
