<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\UserAccessToken;

class YasPaymentService
{
    protected $mnoConfig;

    public function __construct(string $mnoKey = 'yas_soka')
    {
        $this->mnoConfig = config('mno_services.get_mno_config')($mnoKey);
    }

    /**
     * Send charge request to MNO Payment API
     *
     * @param string $requestId
     * @param string $msisdn
     * @param float $amount
     * @return array
     */
    public function sendChargeRequest(
        string $requestId,
        string $msisdn,
        float $amount
    ): array {
        try {
            // Generate reference ID and timestamp
            // $referenceId = $this->generateReferenceId();
            $requestTimeStamp = date('Y-m-d H:i:s');

            // Prepare request payload
            $payload = [
                'username' => $this->mnoConfig['username'],
                'password' => $this->mnoConfig['hashed_password'],
                'sourceAddress' => $this->mnoConfig['source_address'],
                'requestId' => $requestId,
                'requestTimeStamp' => $requestTimeStamp,
                'channel' => $this->mnoConfig['channel'],
                'requestParam' => [
                    'data' => [
                        [
                            'name' => 'OfferCode',
                            'value' => $this->mnoConfig['offer_code']
                        ],
                        [
                            'name' => 'Msisdn',
                            'value' => $msisdn
                        ],
                        [
                            'name' => 'TypeOfService',
                            'value' => $this->mnoConfig['type_of_service']
                        ],
                        [
                            'name' => 'Language',
                            'value' => $this->mnoConfig['language']
                        ],
                        [
                            'name' => 'ChargeAmount',
                            'value' => (string)$amount
                        ],
                        [
                            'name' => 'CpId',
                            'value' => $this->mnoConfig['cp_id']
                        ]
                    ]
                ],
                'operation' => 'Payment'
            ];

            $accessToken = UserAccessToken::latest()->first()->ACCESS_TOKEN; // Retrieve the most recent token
            $headers = [
                'Method: POST',
                'Content-Type: application/json',
                'Content-length: ' . strlen(json_encode($payload)),
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'X-Requested-With: XMLHttpRequest',
                'X-Authorization: Bearer ' . $accessToken,
            ];
            
            // Log::info('...Payment headers:', $headers);
            Log::info('...Payment payload:', $payload);
            
            // $response = Http::withHeaders($headers)
            //     ->withOptions([
            //         'verify' => false,
            //         'timeout' => 2,
            //         'follow_redirects' => true,
            //     ])
            //     ->post($this->mnoConfig['endpoint'], $payload);

            $charging_endpoint = $this->mnoConfig['charging_endpoint'];
            // Log::info('...Charging Endpoint:', ['url' => $charging_endpoint]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $charging_endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $response = curl_exec($ch);
            $curlErrNo = curl_errno($ch);
            $curlError = curl_error($ch);
            $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to send charge request',
                    'http_status' => $httpStatus,
                    'curl_errno' => $curlErrNo,
                    'curl_error' => $curlError,
                ];
            }

            $responseData = json_decode($response, true);
            if (!is_array($responseData)) {
                $responseData = [
                    'raw' => $response,
                ];
            }

            // Process response
            return [
                'status' => 'success',
                'message' => 'Charge request completed',
                'http_status' => $httpStatus,
                'data' => $responseData
            ];

        } catch (\Exception $e) {
            Log::error('Error in YasPaymentService::sendChargeRequest: ' . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate a unique reference ID
     *
     * @return string
     */
    private function generateReferenceId()
    {
        // Generate a 20-digit number using timestamp and random number
        $timestamp = time();
        $random = mt_rand(100000, 999999);
        return $timestamp . $random;
    }
}