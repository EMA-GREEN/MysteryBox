<?php

namespace App\Services;

use App\Models\UserAccessToken;
use Illuminate\Support\Facades\Log;

class YasMpushActivationService
{
    protected array $mnoConfig;

    public function __construct()
    {
        $this->mnoConfig = config('mno_services.yas', []);
    }

    public function sendSubscriptionActivation(string $msisdn): array
    {
        try {
            $requestTimestamp = date('Y-m-d H:i:s');
            $requestId = 'MP' . date('YmdHis') . $msisdn;
            $cpId = $this->mnoConfig['cp_id'];
            $hashedPassword = md5($cpId . $this->mnoConfig['password'] . date('YmdHis'));

            $payload = [
                'requestId' => $requestId,
                'requestTimeStamp' => $requestTimestamp,
                'channel' => $this->mnoConfig['channel'],
                'sourceAddress' => $this->mnoConfig['mpush_source_address'] ?? $this->mnoConfig['source_address'],
                'operation' => 'ACTIVATE',
                'username' => $this->mnoConfig['username'],
                'password' => $hashedPassword,
                'requestParam' => [
                    'data' => [
                        ['name' => 'OfferCode', 'value' => $this->mnoConfig['mpush_offer_code']],
                        ['name' => 'Msisdn', 'value' => $msisdn],
                        ['name' => 'CpId', 'value' => $cpId],
                    ],
                ],
            ];

            $accessToken = UserAccessToken::latest()->first()?->ACCESS_TOKEN;
            if (!$accessToken) {
                return [
                    'status' => 'error',
                    'message' => 'Access token not found',
                    'request_id' => $requestId,
                ];
            }

            $headers = [
                'Method: POST',
                'Content-Type: application/json',
                'Content-length: ' . strlen(json_encode($payload)),
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'X-Requested-With: XMLHttpRequest',
                'X-Authorization: Bearer ' . $accessToken,
            ];

            $endpoint = $this->mnoConfig['activation_endpoint'];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $response = curl_exec($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to send activation request',
                    'request_id' => $requestId,
                    'http_status' => $httpStatus,
                    'curl_error' => $curlError,
                ];
            }

            $responseData = json_decode($response, true);
            if (!is_array($responseData)) {
                $responseData = ['raw' => $response];
            }

            Log::info('Mpush YAS subscription activation response', [
                'msisdn' => $msisdn,
                'request_id' => $requestId,
                'http_status' => $httpStatus,
                'response' => $responseData,
            ]);

            return [
                'status' => 'success',
                'message' => 'Activation request completed',
                'request_id' => $requestId,
                'http_status' => $httpStatus,
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Error in YasMpushActivationService::sendSubscriptionActivation: ' . $e->getMessage(), [
                'msisdn' => $msisdn,
            ]);

            return [
                'status' => 'error',
                'message' => 'An error occurred while processing the activation request',
                'error' => $e->getMessage(),
            ];
        }
    }
}
