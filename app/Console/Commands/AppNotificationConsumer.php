<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MysteryBoxController;
use App\Models\Payment;
use App\Models\MysteryBox;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class AppNotificationConsumer extends Command
{
    protected $signature = 'app:consume-notifications {--consumer-id=1}';
    protected $description = 'Consume app notifications from RabbitMQ queue';

    public function handle()
    {
        $consumerId = $this->option('consumer-id');
        $this->info(sprintf('Starting App Notification Consumer #%d...', $consumerId));

        try {
            // Get queue settings
            $queueSettings = config('queue_settings.app_notification.consumer');
            $queueName = $queueSettings['queue_name'];
            $prefetchCount = $queueSettings['prefetch_count'];

            // Create RabbitMQ connection
            $connection = new AMQPStreamConnection(
                config('rabbitmq.host'),
                config('rabbitmq.port'),
                config('rabbitmq.user'),
                config('rabbitmq.password')
            );

            $channel = $connection->channel();

            // Declare queue
            $channel->queue_declare($queueName, false, true, false, false);

            // Set prefetch count for fair distribution
            $channel->basic_qos(null, $prefetchCount, null);

            $this->info(sprintf('Consumer #%d waiting for app notifications...', $consumerId));

            // Define callback function
            $callback = function (AMQPMessage $message) use ($consumerId) {
                try {
                    $data = json_decode($message->getBody(), true);
                    $msisdn = $data['msisdn'];
                    $action = strtoupper($data['action']);
                    
                    $this->info(sprintf(
                        'Consumer #%d processing app notification - MSISDN: %s, Action: %s',
                        $consumerId,
                        $msisdn,
                        $action
                    ));

                    // Create request for payment initiation
                    $boxTypes = config('boxes.types');
                    $boxTypeIndex = array_search(strtoupper($data['box_type']), array_map('strtoupper', array_column($boxTypes, 'name')));
                    
                    if ($boxTypeIndex === false) {
                        Log::error('Invalid box type received', ['data' => $data]);
                        return;
                    }

                    $paymentType = $action === 'PURCHASE' ? Payment::PAYMENT_TYPE_PURCHASE : Payment::PAYMENT_TYPE_OPEN;
                    
                    // Check if this is an award box that should be opened directly
                    if ($paymentType === Payment::PAYMENT_TYPE_OPEN && isset($data['box_id'])) {
                        $mysteryBox = MysteryBox::find($data['box_id']);
                        
                        if ($mysteryBox && 
                            ($mysteryBox->isMixxPaymentAward() || $mysteryBox->isRenewalAward())) {
                            
                            Log::info('Opening award box directly via APP', [
                                'msisdn' => $msisdn,
                                'box_id' => $mysteryBox->id,
                                'source' => $mysteryBox->source,
                                'payment_type' => $paymentType,
                                'action' => $action
                            ]);
                            
                            // Open the box directly using MysteryBoxController
                            $mysteryBoxController = new MysteryBoxController();
                            $mysteryBoxController->openMysteryBox($msisdn, $mysteryBox->id);
                        } else {
                            // For purchase boxes or other cases, proceed with normal payment flow
                            $request = new Request([
                                'msisdn' => $msisdn,
                                'box_type' => (string)($boxTypeIndex + 0), // Convert to 1-based index(0 for now as currently box starts at index 0)
                                'channel' => 'APP',
                                'payment_type' => $paymentType,
                                'box_id' => $data['box_id']
                            ]);

                            // Initiate payment
                            (new PaymentController)->initiatePayment($request);
                        }
                    } else {
                        // For purchase boxes or when no box_id is provided, proceed with normal payment flow
                        $request = new Request([
                            'msisdn' => $msisdn,
                            'box_type' => (string)($boxTypeIndex + 0), // Convert to 1-based index(0 for now as currently box starts at index 0)
                            'channel' => 'APP',
                            'payment_type' => $paymentType,
                            'box_id' => $data['box_id']
                        ]);

                        // Initiate payment
                        (new PaymentController)->initiatePayment($request);
                    }

                    // Acknowledge message
                    $message->ack();

                    $this->info(sprintf(
                        'Consumer #%d processed app notification - MSISDN: %s, Action: %s',
                        $consumerId,
                        $msisdn,
                        $action
                    ));
                } catch (\Exception $e) {
                    Log::error(sprintf('Consumer #%d error processing app notification: %s', $consumerId, $e->getMessage()));
                    $this->error('Error: ' . $e->getMessage());
                    
                    // Reject message and requeue
                    // $message->reject(true);
                }
            };

            // Start consuming
            $channel->basic_consume($queueName, '', false, false, false, false, $callback);

            while ($channel->is_consuming()) {
                $channel->wait();
            }

            // Close connection
            $channel->close();
            $connection->close();
        } catch (\Exception $e) {
            Log::error(sprintf('Consumer #%d error in app notification consumer: %s', $consumerId, $e->getMessage()));
            // $this->error('Error: ' . $e->getMessage());
        }
    }
}