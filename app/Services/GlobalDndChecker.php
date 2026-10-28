<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class GlobalDndChecker
{
    public const RESULT_ALLOWED = 'allowed';
    public const RESULT_BLOCKED = 'blocked';
    public const RESULT_UNAVAILABLE = 'unavailable';

    /**
     * @return array{result: string, reason: ?string}
     */
    public function check(string $msisdn): array
    {
        if (!config('services.dnd.enabled', true)) {
            return ['result' => self::RESULT_ALLOWED, 'reason' => null];
        }

        $msisdn = preg_replace('/\D/', '', $msisdn) ?? '';
        if ($msisdn === '') {
            return [
                'result' => self::RESULT_BLOCKED,
                'reason' => 'MSISDN is on Yas global DND list',
            ];
        }

        $connection = config('services.dnd.redis_connection', 'dnd');
        $key = config('services.dnd.redis_key', 'yas:dnd');
        $password = config("database.redis.{$connection}.password");

        try {
            $blocked = (bool) Redis::connection($connection)->sismember($key, $msisdn);

            return $blocked
                ? [
                    'result' => self::RESULT_BLOCKED,
                    'reason' => 'MSISDN is on Yas global DND list',
                ]
                : ['result' => self::RESULT_ALLOWED, 'reason' => null];
        } catch (\Throwable $e) {
            Log::error('Yas DND redis check failed', [
                'msisdn' => $msisdn,
                'error' => $e->getMessage(),
                'host' => config("database.redis.{$connection}.host"),
                'has_password' => filled($password),
            ]);

            return $this->unavailableResult();
        }
    }

    public function isBlocked(string $msisdn): bool
    {
        $result = $this->check($msisdn)['result'];

        return $result === self::RESULT_BLOCKED || $result === self::RESULT_UNAVAILABLE;
    }

    private function unavailableResult(): array
    {
        if (config('services.dnd.fail_closed', true)) {
            return [
                'result' => self::RESULT_UNAVAILABLE,
                'reason' => 'Yas DND redis unavailable (check DND_REDIS_PASSWORD / connectivity)',
            ];
        }

        return ['result' => self::RESULT_ALLOWED, 'reason' => null];
    }
}
