<?php

namespace App\Services;

use App\Models\YasFreeBoxDailyCounter;
use App\Models\YasFreeBoxDailyUsage;
use App\Models\YasFreeBoxOpening;
use App\Models\Winner;
use App\Models\WinnerEntry;
use App\Models\WinnerWalletDisbursementNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class YasFreeBoxService
{
    public function today(): string
    {
        return Carbon::now(config('yas_free_box.timezone'))->toDateString();
    }

    public function allowedSources(): array
    {
        $sources = config('yas_free_box.sources', []);

        return array_keys(array_filter($sources, fn ($cfg) => !empty($cfg['enabled'])));
    }

    public function assertSource(string $source): void
    {
        if (!in_array($source, $this->allowedSources(), true)) {
            throw new RuntimeException('INVALID_SOURCE', 422);
        }
    }

    public function getStatus(string $msisdn, string $source): array
    {
        $this->assertSource($source);

        $dailyLimit = (int) config('yas_free_box.daily_opens_per_msisdn');
        $opensUsed = (int) (YasFreeBoxDailyUsage::where('msisdn', $msisdn)
            ->where('open_date', $this->today())
            ->value('opens_used') ?? 0);
        $remaining = max(0, $dailyLimit - $opensUsed);
        $freeActive = config('yas_free_box.enabled') && $remaining > 0;

        $boxes = [];
        $freeCfg = config('yas_free_box.box');
        $boxes[] = [
            'type' => $freeCfg['name'],
            'display_name' => $freeCfg['display_name'],
            'amount' => 0,
            'active' => $freeActive,
            'free' => true,
            'source' => $source,
        ];

        foreach (config('boxes.types') as $box) {
            if (!empty($box['hidden']) || !empty($box['yas_free']) || !empty($box['free'])) {
                continue;
            }
            $boxes[] = [
                'type' => $box['name'],
                'display_name' => $box['display_name'] ?? $box['name'],
                'amount' => $box['amount'],
                'active' => true,
                'free' => false,
            ];
        }

        return [
            'free_box_active' => $freeActive,
            'opens_used' => $opensUsed,
            'opens_remaining' => $remaining,
            'daily_limit' => $dailyLimit,
            'source' => $source,
            'source_label' => config("yas_free_box.sources.{$source}.label", $source),
            'boxes' => $boxes,
        ];
    }

    public function open(string $msisdn, string $source, ?string $requestId = null): array
    {
        $this->assertSource($source);

        if (!config('yas_free_box.enabled')) {
            throw new RuntimeException('YAS_FREE_BOX_DISABLED', 403);
        }

        $requestId = $requestId ?: $this->makeRequestId($msisdn);
        $existing = YasFreeBoxOpening::where('request_id', $requestId)->first();
        if ($existing) {
            return $this->openingResponse($existing, $msisdn, $source);
        }

        $dailyLimit = (int) config('yas_free_box.daily_opens_per_msisdn');
        $interval = (int) config('yas_free_box.winning.interval');
        $maxWinners = (int) config('yas_free_box.winning.max_winners_per_day');
        $prizeAmount = (float) config('yas_free_box.winning.prize_amount');
        $prizeType = config('yas_free_box.winning.prize_type', 'cash');
        $budget = (float) config('yas_free_box.daily_prize_budget');
        $today = $this->today();

        return DB::transaction(function () use (
            $msisdn, $source, $requestId, $dailyLimit, $interval, $maxWinners,
            $prizeAmount, $prizeType, $budget, $today
        ) {
            YasFreeBoxDailyUsage::firstOrCreate(
                ['msisdn' => $msisdn, 'open_date' => $today],
                ['opens_used' => 0]
            );

            $usage = YasFreeBoxDailyUsage::where('msisdn', $msisdn)
                ->where('open_date', $today)
                ->lockForUpdate()
                ->first();

            if ($usage->opens_used >= $dailyLimit) {
                throw new RuntimeException('DAILY_LIMIT_REACHED', 429);
            }

            YasFreeBoxDailyCounter::firstOrCreate(
                ['open_date' => $today],
                ['open_count' => 0, 'winners_count' => 0, 'total_prize_amount' => 0]
            );

            $counter = YasFreeBoxDailyCounter::where('open_date', $today)
                ->lockForUpdate()
                ->first();

            $openIndex = $counter->open_count + 1;
            $userOpenNumber = $usage->opens_used + 1;

            $isWinner = $openIndex % $interval === 0
                && (int) ($openIndex / $interval) <= $maxWinners
                && $counter->winners_count < $maxWinners
                && ((float) $counter->total_prize_amount + $prizeAmount) <= $budget;

            $awardedPrize = $isWinner ? $prizeAmount : 0;
            $refPrefix = config('yas_free_box.payout.reference_prefix', 'YASF');
            $referenceId = $isWinner
                ? $refPrefix . $msisdn . date('YmdHis') . Str::upper(Str::random(4))
                : null;

            $opening = YasFreeBoxOpening::create([
                'msisdn' => $msisdn,
                'source' => $source,
                'open_date' => $today,
                'open_index' => $openIndex,
                'user_open_number' => $userOpenNumber,
                'is_winner' => $isWinner,
                'prize_amount' => $awardedPrize,
                'prize_type' => $isWinner ? $prizeType : null,
                'status' => 'opened',
                'reference_id' => $referenceId,
                'request_id' => $requestId,
            ]);

            $usage->opens_used = $userOpenNumber;
            $usage->save();

            $counter->open_count = $openIndex;
            if ($isWinner) {
                $counter->winners_count += 1;
                $counter->total_prize_amount = (float) $counter->total_prize_amount + $awardedPrize;
            }
            $counter->save();

            if ($isWinner && config('yas_free_box.payout.enabled')) {
                $this->enqueuePayout($opening);
            }

            return $this->openingResponse($opening, $msisdn, $source);
        });
    }

    private function enqueuePayout(YasFreeBoxOpening $opening): void
    {
        $boxName = config('yas_free_box.box.name', 'YasFree');
        $prizeLabel = 'Tsh ' . number_format((float) $opening->prize_amount, 0, '.', ',');

        $winnerEntry = WinnerEntry::create([
            'msisdn' => $opening->msisdn,
            'box_type' => $boxName,
            'prize' => $prizeLabel,
            'prize_type' => 'wallet',
            'amount' => $opening->prize_amount,
            'awarded_at' => now(),
            'box_id' => $opening->id,
        ]);

        Winner::create([
            'msisdn' => $opening->msisdn,
            'winner_type' => 'wallet',
            'prize_description' => $prizeLabel,
            'prize_value' => $opening->prize_amount,
            'source_table' => 'yas_free_box_openings',
            'source_id' => $opening->id,
            'box_id' => $opening->id,
            'box_type' => $boxName,
            'airtime_disbursement_status' => 'pending',
            'wallet_disbursement_status' => 'pending',
            'won_at' => now(),
        ]);

        WinnerWalletDisbursementNotification::create([
            'msisdn' => $opening->msisdn,
            'amount' => $opening->prize_amount,
            'box_type' => $boxName,
            'box_id' => (string) $opening->id,
            'winner_id' => (string) $winnerEntry->id,
            'request_id' => $opening->reference_id,
            'publish_status' => false,
        ]);
    }

    private function openingResponse(YasFreeBoxOpening $opening, string $msisdn, string $source): array
    {
        $dailyLimit = (int) config('yas_free_box.daily_opens_per_msisdn');
        $opensUsed = (int) (YasFreeBoxDailyUsage::where('msisdn', $msisdn)
            ->where('open_date', $this->today())
            ->value('opens_used') ?? 0);

        $isWin = (bool) $opening->is_winner;
        $prizeAmount = (float) $opening->prize_amount;

        return [
            'result' => $isWin ? 'win' : 'loss',
            'open_index' => (int) $opening->open_index,
            'prize_amount' => $prizeAmount,
            'prize_type' => $isWin ? ($opening->prize_type ?? config('yas_free_box.winning.prize_type')) : null,
            'opens_remaining' => max(0, $dailyLimit - $opensUsed),
            'source' => $opening->source ?: $source,
            'message' => $isWin
                ? 'Umeshinda Tsh ' . number_format($prizeAmount, 0, '.', ',')
                : 'Asante kwa kushiriki',
            'request_id' => $opening->request_id,
        ];
    }

    private function makeRequestId(string $msisdn): string
    {
        return config('yas_free_box.payout.reference_prefix', 'YASF')
            . $msisdn
            . date('YmdHis')
            . Str::upper(Str::random(6));
    }
}
