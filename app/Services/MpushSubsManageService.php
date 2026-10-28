<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MpushSubsManageService
{
    public function notifySubscription(string $msisdn): array
    {
        return $this->sendNotification($msisdn, 'SUBSCRIPTION');
    }

    public function notifyUnsubscription(string $msisdn): array
    {
        return $this->sendNotification($msisdn, 'UNSUBSCRIPTION');
    }

    private function sendNotification(string $msisdn, string $activityType): array
    {
        try {
            $payload = [
                'msisdn' => $msisdn,
                'serviceID' => config('mpush.service_id', 'MysteryBox'),
                'activityType' => $activityType,
            ];

            $jsonRequest = json_encode($payload);
            $headers = [
                'Method: POST',
                'Content-Type: application/json',
                'Content-length: ' . strlen($jsonRequest),
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ];

            $url = config('mpush.subs_manage_url');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_TIMEOUT, config('mpush.timeout', 10));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonRequest);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $response = curl_exec($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                Log::error('Mpush subs manage request failed', [
                    'msisdn' => $msisdn,
                    'activity_type' => $activityType,
                    'http_status' => $httpStatus,
                    'curl_error' => $curlError,
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Failed to send subs manage notification',
                    'http_status' => $httpStatus,
                    'curl_error' => $curlError,
                ];
            }

            $responseData = json_decode($response, true);
            if (!is_array($responseData)) {
                $responseData = ['raw' => $response];
            }

            Log::info('Mpush subs manage notification sent', [
                'msisdn' => $msisdn,
                'activity_type' => $activityType,
                'http_status' => $httpStatus,
                'response' => $responseData,
            ]);

            return [
                'status' => 'success',
                'message' => 'Subs manage notification sent',
                'http_status' => $httpStatus,
                'data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Mpush subs manage error: ' . $e->getMessage(), [
                'msisdn' => $msisdn,
                'activity_type' => $activityType,
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
