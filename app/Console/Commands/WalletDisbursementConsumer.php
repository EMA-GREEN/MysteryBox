<?php

namespace App\Console\Commands;

use App\Models\WinnerWalletDisbursement;
use App\Models\Winner;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WalletDisbursementConsumer extends Command
{
    protected $signature = 'wallet:disburse-consume {--consumer-id= : The ID of this consumer instance}';
    protected $description = 'Consume wallet disbursement messages from queue';

    private $connection;
    private $channel;
    private $queueName;
    private $prefetchCount;
    private $consumerNumber;

    public function __construct()
    {
        parent::__construct();
        $this->queueName = config('queue_settings.wallet_disbursement.consumer.queue_name');
        $this->prefetchCount = config('queue_settings.wallet_disbursement.consumer.prefetch_count');
    }

    public function handle()
    {
        $this->consumerNumber = $this->option('consumer-id') ?? '1';
        $this->info('Starting wallet disbursement consumer...');

        while (true) {
            try {
                if (!$this->connection || !$this->connection->isConnected()) {
                    $this->connect();
                }

                $this->channel->basic_qos(null, $this->prefetchCount, null);
                $this->channel->basic_consume($this->queueName, '', false, false, false, false, [$this, 'processMessage']);

                while ($this->channel->is_consuming()) {
                    $this->channel->wait();
                }
            } catch (\Exception $e) {
                Log::error('Error in wallet disbursement consumer: ' . $e->getMessage());
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

    private function prepareJsonRequest($disbursement, $referenceId = null)
    {
        // Use provided reference_id or generate from disbursement
        $reference = $referenceId ?: 'PYSCPW' . $disbursement->msisdn . date('YmdHis');
        
        return [
            'disbursements' => [
                [
                    'amount' => (int) $disbursement->payout_amount,
                    'currency' => config('payscoop.currency', 'TZS'),
                    'msisdn' => $disbursement->msisdn,
                    'reference' => $reference
                ]
            ]
        ];
    }

    private function getAuthorizationHeader()
    {
        $apiKey = config('payscoop.api_key');
        $apiSecret = config('payscoop.api_secret');
        $credentials = base64_encode($apiKey . ':' . $apiSecret);
        return 'Basic ' . $credentials;
    }

    private function sendWalletRequest($requestData)
    {
        $url = config('payscoop.base_url') . config('payscoop.endpoints.wallet');
        
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => $this->getAuthorizationHeader()
        ])
        ->timeout(config('payscoop.timeout', 30))
        ->post($url, $requestData);
        
        return $response;
    }

    private function getDummyResponse()
    {
        return [
            'message' => 'Disbursement created successfully',
            'status' => 'success',
            'statusCode' => 200
        ];
    }

    public function processMessage($msg)
    {
        try {
            $data = json_decode($msg->body, true);
            
            // Calculate payout amount after tax
            $taxPercent = config('queue_settings.wallet_disbursement.tax_percent', 15);
            $payoutAmount = $data['amount'] * (1 - ($taxPercent / 100));
            
            // Generate reference_id once
            $referenceId = 'PYSCPW' . $data['msisdn'] . date('YmdHis');
            
            // Create disbursement record
            $disbursement = WinnerWalletDisbursement::create([
                'msisdn' => $data['msisdn'],
                'amount' => $data['amount'],
                'payout_amount' => $payoutAmount,
                'tax_percent' => $taxPercent,
                'reference_id' => $referenceId,
                'box_type' => $data['box_type'],
                'box_id' => $data['box_id'],
                'winner_id' => $data['winner_id'],
                'request_sent_time' => now()
            ]);

            $this->info("Consumer #{$this->consumerNumber} processing wallet disbursement - MSISDN: {$data['msisdn']}, Amount: {$data['amount']}, Tax: {$taxPercent}%, Payout: {$payoutAmount}");

            // Prepare JSON request with the same reference_id
            $requestData = $this->prepareJsonRequest($disbursement, $referenceId);
            
            // Log request
            Log::info('Wallet disbursement request', [
                'reference_id' => $disbursement->reference_id,
                'request' => $requestData
            ]);

            // Comment out actual request for testing
            $response = $this->sendWalletRequest($requestData);
            $responseData = $response->json();

            // Use dummy response for testing
            // $responseData = $this->getDummyResponse();
            
            // Update disbursement record with acknowledgment response
            $disbursement->update([
                'ack_status' => $responseData['status'] ?? null,
                'ack_message' => $responseData['message'] ?? null,
                'ack_received_time' => now()
            ]);

            // Update winners table automatically
            $this->updateWinnerStatus($disbursement, $responseData);

            // Log response
            Log::info('Wallet disbursement response', [
                'reference_id' => $disbursement->reference_id,
                'response' => $responseData
            ]);

            $msg->ack();
        } catch (\Exception $e) {
            Log::error('Error processing wallet disbursement: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            // $msg->reject();
        }
    }

    private function updateWinnerStatus($disbursement, $responseData)
    {
        try {
            $updateData = [
                'wallet_disbursement_reference_id' => $disbursement->reference_id,
                'payout_amount' => $disbursement->payout_amount,
                'tax_percent' => $disbursement->tax_percent,
                'updated_at' => now()
            ];
            
            // Set wallet-specific status based on acknowledgment response
            if ($disbursement->ack_status === 'success') {
                $updateData['wallet_disbursement_status'] = 'success';
                $updateData['wallet_disbursed_at'] = $disbursement->ack_received_time;
            } elseif (in_array($disbursement->ack_status, ['failed', 'rejected', 'cancelled'])) {
                $updateData['wallet_disbursement_status'] = 'failed';
            } else {
                $updateData['wallet_disbursement_status'] = 'processing';
            }
            
            Winner::where('source_table', 'winner_entries')
                ->where('source_id', $disbursement->winner_id)
                ->where('winner_type', 'wallet')
                ->update($updateData);
                
            Log::info('Winner wallet status updated', [
                'winner_id' => $disbursement->winner_id,
                'reference_id' => $disbursement->reference_id,
                'status' => $updateData['wallet_disbursement_status']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update winner wallet status', [
                'winner_id' => $disbursement->winner_id,
                'reference_id' => $disbursement->reference_id,
                'error' => $e->getMessage()
            ]);
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