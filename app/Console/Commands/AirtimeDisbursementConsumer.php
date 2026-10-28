<?php

namespace App\Console\Commands;

use App\Models\WinnerAirtimeDisbursement;
use App\Models\Winner;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class AirtimeDisbursementConsumer extends Command
{
    protected $signature = 'airtime:disburse-consume {--consumer-id= : The ID of this consumer instance}';
    protected $description = 'Consume airtime disbursement messages from queue';

    private $connection;
    private $channel;
    private $queueName;
    private $prefetchCount;
    private $consumerNumber;

    public function __construct()
    {
        parent::__construct();
        $this->queueName = config('queue_settings.airtime_disbursement.consumer.queue_name');
        $this->prefetchCount = config('queue_settings.airtime_disbursement.consumer.prefetch_count');
    }

    public function handle()
    {
        $this->consumerNumber = $this->option('consumer-id') ?? '1';
        $this->info('Starting airtime disbursement consumer...');

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
                Log::error('Error in airtime disbursement consumer: ' . $e->getMessage());
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
        $reference = $referenceId ?: 'PYSCPA' . $disbursement->msisdn . date('YmdHis');
        
        return [
            'disbursements' => [
                [
                    'amount' => (int) $disbursement->amount,
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

    private function sendAirtimeRequest($requestData)
    {
        $url = config('payscoop.base_url') . config('payscoop.endpoints.airtime');
        
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
            'message' => 'Airtime Request created successfully',
            'status' => 'success',
            'statusCode' => 200
        ];
    }

    public function processMessage($msg)
    {
        try {
            $data = json_decode($msg->body, true);
            
            // Generate reference_id once
            $referenceId = 'PYSCPA' . $data['msisdn'] . date('YmdHis');
            
            // Create disbursement record
            $disbursement = WinnerAirtimeDisbursement::create([
                'msisdn' => $data['msisdn'],
                'amount' => $data['amount'],
                'reference_id' => $referenceId,
                'box_type' => $data['box_type'],
                'box_id' => $data['box_id'],
                'winner_id' => $data['winner_id'],
                'request_id' => $data['request_id'],
                'request_sent_time' => now()
            ]);

            $this->info("Consumer #{$this->consumerNumber} processing airtime disbursement - MSISDN: {$data['msisdn']}, Amount: {$data['amount']}");

            // Prepare JSON request with the same reference_id
            $requestData = $this->prepareJsonRequest($disbursement, $referenceId);
            
            // Log request
            Log::info('Airtime disbursement request', [
                'reference_id' => $disbursement->reference_id,
                'request' => $requestData
            ]);

            // Comment out actual request for testing
            $response = $this->sendAirtimeRequest($requestData);
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
            Log::info('Airtime disbursement response', [
                'reference_id' => $disbursement->reference_id,
                'response' => $responseData
            ]);

            $msg->ack();
        } catch (\Exception $e) {
            Log::error('Error processing airtime disbursement: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
            // $msg->reject();
        }
    }

    private function updateWinnerStatus($disbursement, $responseData)
    {
        try {
            $updateData = [
                'airtime_disbursement_reference_id' => $disbursement->reference_id,
                'updated_at' => now()
            ];
            
            // Set airtime-specific status based on acknowledgment response
            if ($disbursement->ack_status === 'success') {
                $updateData['airtime_disbursement_status'] = 'success';
                $updateData['airtime_disbursed_at'] = $disbursement->ack_received_time;
            } elseif (in_array($disbursement->ack_status, ['failed', 'rejected', 'cancelled'])) {
                $updateData['airtime_disbursement_status'] = 'failed';
            } else {
                $updateData['airtime_disbursement_status'] = 'processing';
            }
            
            Winner::where('source_table', 'winner_entries')
                ->where('source_id', $disbursement->winner_id)
                ->where('winner_type', 'airtime')
                ->update($updateData);
                
            Log::info('Winner airtime status updated', [
                'winner_id' => $disbursement->winner_id,
                'reference_id' => $disbursement->reference_id,
                'status' => $updateData['airtime_disbursement_status']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update winner airtime status', [
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