<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lab MSISDNs: proxy MO + payment callbacks to mystrybox-backend (Go API).
 */
class MysteryBoxGoLabForward
{
    private const LAB_MSISDNS = ['255656692469', '255676589824'];

    public static function isLabMsisdn(?string $msisdn): bool
    {
        if ($msisdn === null || $msisdn === '') {
            return false;
        }
        $msisdn = str_replace('+', '', $msisdn);

        return in_array($msisdn, self::LAB_MSISDNS, true);
    }

    public static function baseUrl(): string
    {
        return rtrim(env('MYSTERYBOX_GO_API_URL', 'http://102.218.75.31:8081'), '/');
    }

    public static function forwardReceiveSms(Request $request)
    {
        $url = self::baseUrl() . '/api/sms/receive-sms';
        $response = Http::timeout(15)->get($url, $request->query());
        Log::info('Forwarded receive-sms to Go backend', ['status' => $response->status()]);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public static function forwardReceiveMo(Request $request)
    {
        $url = self::baseUrl() . '/api/sms/receive-mo';
        $response = Http::timeout(15)->get($url, $request->query());
        Log::info('Forwarded receive-mo to Go backend', ['status' => $response->status()]);

        return response()->json($response->json() ?? ['message' => $response->body()], $response->status());
    }

    public static function forwardPaymentCallback(array $payload)
    {
        $url = self::baseUrl() . '/api/payments/payment-callback';
        $response = Http::timeout(20)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);
        Log::info('Forwarded payment-callback to Go backend', [
            'status' => $response->status(),
            'requestId' => $payload['requestId'] ?? null,
        ]);

        return response()->json($response->json() ?? [], $response->status());
    }

    public static function msisdnFromCallbackPayload(array $data): ?string
    {
        if (!isset($data['requestParam']['data']) || !is_array($data['requestParam']['data'])) {
            return null;
        }
        foreach ($data['requestParam']['data'] as $item) {
            if (isset($item['name'], $item['value']) && strcasecmp($item['name'], 'MSISDN') === 0) {
                return str_replace('+', '', (string) $item['value']);
            }
        }

        return null;
    }
}
