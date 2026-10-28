<?php

namespace App\Console\Commands;

use App\Models\RenewalSubscriber;
use App\Models\Subscriber;
use App\Services\MpushSubsManageService;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class MpushSubsInitNotificationConsumer extends Command
{
    protected $signature = 'mpush:consume-subs-init {--consumer-id=1}';
    protected $description = 'Consume mpush subscription init notifications and process subscription';

    protected $connection;
    protected $channel;
    protected $queueName;
    protected $prefetchCount;

    public function __construct(
        private MpushSubsManageService $mpushSubsManageService,
        private SmsService $smsService
    ) {
        parent::__construct();
        $settings = config('queue_settings.mpush_subs_init_notification.consumer');
        $this->queueName = $settings['queue_name'];
        $this->prefetchCount = $settings['prefetch_count'];
    }

    protected function connect(): void
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

    public function handle(): void
    {
        $consumerId = $this->option('consumer-id');
        $this->info("Starting Mpush Subs Init Notification Consumer #{$consumerId}...");

        try {
            $this->connect();

            $callback = function ($msg) use ($consumerId) {
                try {
                    $data = json_decode($msg->body, true);

                    if (!$data || empty($data['msisdn'])) {
                        Log::error('Invalid mpush subs init message', ['body' => $msg->body]);
                        $msg->ack();
                        return;
                    }

                    $msisdn = (string) $data['msisdn'];
                    $channel = $data['channel'] ?? 'USSD';

                    $this->info(sprintf(
                        'Consumer #%d processing MSISDN: %s, Record ID: %s',
                        $consumerId,
                        $msisdn,
                        $data['record_id'] ?? 'N/A'
                    ));

                    $this->processSubscription($msisdn, $channel);
                    $msg->ack();
                } catch (\Exception $e) {
                    Log::error('Error processing mpush subs init message: ' . $e->getMessage(), [
                        'body' => $msg->body,
                    ]);
                    $msg->ack();
                }
            };

            $this->channel->basic_consume($this->queueName, '', false, false, false, false, $callback);

            while ($this->channel->is_consuming()) {
                $this->channel->wait();
            }
        } catch (\Exception $e) {
            Log::error('MpushSubsInitNotificationConsumer error: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        } finally {
            if ($this->channel && $this->channel->is_open()) {
                $this->channel->close();
            }
            if ($this->connection && $this->connection->isConnected()) {
                $this->connection->close();
            }
        }
    }

    /**
     * Follow existing SMS subscription process:
     * findOrCreate subscriber, sync renewal_subscriber, reactivate/welcome as needed.
     */
    private function processSubscription(string $msisdn, string $channel): void
    {
        $subscriber = Subscriber::findOrCreate($msisdn, [
            'sub_channel' => $channel,
        ]);

        if (!$subscriber->activity_status) {
            $subscriber->update(['activity_status' => 'ACTIVE']);
            $subscriber->refresh();
        }
        if (!$subscriber->dnd_status) {
            $subscriber->update(['dnd_status' => 'OFF']);
            $subscriber->refresh();
        }

        // Skip blacklisted subscribers
        if (!$subscriber->wasRecentlyCreated && $subscriber->dnd_status === 'ON') {
            Log::info("Skipping mpush subs init for blacklisted subscriber (dnd_status=ON): {$msisdn}");
            return;
        }

        // Sync renewal_subscriber with subscriber status
        $renewalSubscriber = RenewalSubscriber::where('msisdn', $msisdn)->first();
        if (!$renewalSubscriber) {
            RenewalSubscriber::create([
                'msisdn' => $msisdn,
                'activity_status' => $subscriber->activity_status ?? 'ACTIVE',
                'dnd_status' => $subscriber->dnd_status ?? 'OFF',
                'priority' => 1,
                'retry_count' => 0,
                'publish_status' => false,
                'last_renewal_billing' => null,
                'next_retrial_period' => null,
            ]);
        } else {
            $renewalSubscriber->update([
                'activity_status' => $subscriber->activity_status ?? 'ACTIVE',
                'dnd_status' => $subscriber->dnd_status ?? 'OFF',
            ]);
        }

        // Reactivate INACTIVE subscriber
        if ($subscriber->activity_status === 'INACTIVE') {
            $subscriber->update(['activity_status' => 'ACTIVE']);
            Log::info("Subscriber reactivated via mpush: {$msisdn}");
            $this->mpushSubsManageService->notifySubscription($msisdn);

            DB::table('renewal_subscribers')
                ->where('msisdn', $msisdn)
                ->where('activity_status', 'INACTIVE')
                ->update(['activity_status' => 'ACTIVE']);
            Log::info("Renewal subscriber reactivated via mpush: {$msisdn}");

            $this->smsService->sendSms($msisdn, config('messages.sms.welcome_again_message'));
            $this->smsService->sendSms($msisdn, config('messages.sms.terms_message'));
            return;
        }

        // New subscriber welcome flow
        if ($subscriber->wasRecentlyCreated) {
            $this->mpushSubsManageService->notifySubscription($msisdn);
            $this->smsService->sendSms($msisdn, config('messages.sms.welcome_message'));
            $this->smsService->sendSms($msisdn, config('messages.sms.terms_message'));
            Log::info("New subscriber processed via mpush: {$msisdn}");
            return;
        }

        $subscriber->update(['last_interaction' => now()]);
    }
}
