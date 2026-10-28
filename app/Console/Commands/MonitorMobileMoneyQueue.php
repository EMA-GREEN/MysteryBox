<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorMobileMoneyQueue extends Command
{
    protected $signature = 'monitor:mobile-money-queue {--interval=10 : Check interval in seconds}';
    protected $description = 'Monitor mobile money queue performance and health';

    public function handle()
    {
        $interval = (int) $this->option('interval');
        
        $this->info("Starting Mobile Money Queue Monitor (checking every {$interval}s)");
        $this->info("Press Ctrl+C to stop monitoring");
        
        while (true) {
            $this->checkQueueHealth();
            sleep($interval);
        }
    }

    private function checkQueueHealth()
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        
        try {
            // Check Redis queue sizes
            $redis = Redis::connection();
            
            $highPrioritySize = $redis->llen('queues:mobile_money_high_priority');
            $mediumPrioritySize = $redis->llen('queues:mobile_money_medium_priority');
            $lowPrioritySize = $redis->llen('queues:mobile_money_low_priority');
            
            // Check failed jobs count
            $failedJobs = DB::table('failed_jobs')
                ->where('payload', 'like', '%ProcessMobileMoneyNotification%')
                ->where('failed_at', '>=', now()->subHour())
                ->count();
            
            // Check recent processing rate
            $recentNotifications = DB::table('mobile_money_transaction_notifications')
                ->where('created_at', '>=', now()->subMinute())
                ->count();
            
            // Display status
            $this->line("[$timestamp] Queue Status:");
            $this->line("  High Priority: {$highPrioritySize} jobs");
            $this->line("  Medium Priority: {$mediumPrioritySize} jobs");
            $this->line("  Low Priority: {$lowPrioritySize} jobs");
            $this->line("  Failed (last hour): {$failedJobs} jobs");
            $this->line("  Processed (last minute): {$recentNotifications} notifications");
            
            // Calculate TPS
            $tps = $recentNotifications;
            $this->line("  Current TPS: {$tps}");
            
            // Alert conditions
            $totalPending = $highPrioritySize + $mediumPrioritySize + $lowPrioritySize;
            
            if ($totalPending > 1000) {
                $this->error("  ⚠️  HIGH QUEUE BACKLOG: {$totalPending} pending jobs");
                Log::warning('High queue backlog detected', [
                    'total_pending' => $totalPending,
                    'high_priority' => $highPrioritySize,
                    'medium_priority' => $mediumPrioritySize,
                    'low_priority' => $lowPrioritySize
                ]);
            }
            
            if ($failedJobs > 50) {
                $this->error("  ⚠️  HIGH FAILURE RATE: {$failedJobs} failed jobs in last hour");
                Log::error('High failure rate detected', ['failed_jobs_count' => $failedJobs]);
            }
            
            if ($tps > 350) {
                $this->warn("  ⚠️  HIGH TPS: {$tps} (above 300 target)");
            } elseif ($tps >= 250) {
                $this->info("  ✅ GOOD TPS: {$tps}");
            }
            
            $this->line(""); // Empty line for readability
            
        } catch (\Exception $e) {
            $this->error("Monitor error: " . $e->getMessage());
            Log::error('Queue monitor error', ['error' => $e->getMessage()]);
        }
    }
}