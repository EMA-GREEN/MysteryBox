<?php

namespace App\Console\Commands;

use App\Models\WalletDisbursementCallback;
use App\Models\WinnerWalletDisbursement;
use App\Models\Winner;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WalletDisbursementCallbackConsumer extends Command
{
    protected $signature = 'wallet:disbursement-callback:consume {--consumer-id=1}';
    protected $description = 'Consume wallet disbursement callbacks from RabbitMQ queue';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $prefetchCount;
    protected $consumerNumber;

    public function __construct()
    {
        parent::__construct();
        $queueSettings = config('queue_settings.wallet_disbursement_callback.consumer');
        $this->queueName = $queueSettings['queue_name'];
        $this->prefetchCount = $queueSettings['prefetch_count'];
    }

    public function handle()
    {
        $this->consumerNumber = $this->option('consumer-id');
        $this->info("Starting Wallet Disbursement Callback Consumer #{$this->consumerNumber}...");

        try {
            $this->connect();

            $callback = function ($msg) {
                try {
                    $data = json_decode($msg->body, true);
                    
                    $this->info("Consumer #{$this->consumerNumber} processing wallet disbursement callback for MSISDN: {$data['msisdn']}");
                    Log::info("Processing wallet disbursement callback", $data);

                    // Validate required fields
                    if (!isset($data['callback_id']) || !isset($data['reference_id']) || !isset($data['msisdn'])) {
                        throw new \Exception('Missing required fields: callback_id, reference_id, or msisdn');
                    }

                    // Find the callback record
                    $callback = WalletDisbursementCallback::find($data['callback_id']);
                    if (!$callback) {
                        throw new \Exception("Callback record not found for ID: {$data['callback_id']}");
                    }

                                         // Update disbursement record
                     $this->updateWalletDisbursement($callback);

                    $this->info("Consumer #{$this->consumerNumber} successfully processed wallet disbursement callback for MSISDN: {$data['msisdn']}");
                    Log::info("Wallet disbursement callback processed successfully", [
                        'callback_id' => $callback->id,
                        'reference_id' => $callback->reference_id,
                        'msisdn' => $callback->msisdn
                    ]);

                    $msg->ack();
                } catch (\Exception $e) {
                    $this->error("Consumer #{$this->consumerNumber} error processing message: {$e->getMessage()}");
                    Log::error("Error processing wallet disbursement callback: " . $e->getMessage(), [
                        'data' => $data ?? null
                    ]);
                    $msg->ack(); // Acknowledge to remove from queue
                }
            };

            $this->channel->basic_qos(null, $this->prefetchCount, null);
            $this->channel->basic_consume($this->queueName, '', false, false, false, false, $callback);

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            $this->error("Consumer #{$this->consumerNumber} error: {$e->getMessage()}");
            Log::error("Error in wallet disbursement callback consumer: " . $e->getMessage());
        } finally {
            $this->disconnect();
        }
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

    protected function updateWalletDisbursement($callback)
    {
        DB::transaction(function () use ($callback) {
            // Only update disbursement record if reference_id is provided
            if ($callback->reference_id) {
                $disbursement = WinnerWalletDisbursement::where('reference_id', $callback->reference_id)->first();
                
                if ($disbursement) {
                    $disbursement->update([
                        'callback_status' => $callback->status,
                        'callback_message' => $callback->message,
                        'callback_received_time' => $callback->callback_received_at
                    ]);

                    // Update winner status
                    $this->updateWinnerWalletStatus($disbursement, $callback);
                } else {
                    Log::warning("Wallet disbursement not found for reference_id: {$callback->reference_id}");
                }
            }
        });
    }

    protected function updateWinnerWalletStatus($disbursement, $callback)
    {
        $updateData = [
            'wallet_disbursement_reference_id' => $disbursement->reference_id,
            'updated_at' => now()
        ];

        // Set wallet-specific status based on callback
        if ($callback->status === 'SUCCESS') {
            $updateData['wallet_disbursement_status'] = 'success';
            $updateData['wallet_disbursed_at'] = $callback->callback_received_at;
        } elseif ($callback->status && in_array($callback->status, ['FAILED', 'REJECTED', 'CANCELLED'])) {
            $updateData['wallet_disbursement_status'] = 'failed';
        } else {
            $updateData['wallet_disbursement_status'] = 'processing';
        }

        Winner::where('source_table', 'winner_entries')
            ->where('source_id', $disbursement->winner_id)
            ->where('winner_type', 'wallet')
            ->update($updateData);

        Log::info('Winner wallet status updated from callback', [
            'winner_id' => $disbursement->winner_id,
            'reference_id' => $disbursement->reference_id,
            'status' => $updateData['wallet_disbursement_status']
        ]);
    }

    protected function disconnect()
    {
        if ($this->channel && $this->channel->is_open()) {
            $this->channel->close();
        }
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
    }
}
