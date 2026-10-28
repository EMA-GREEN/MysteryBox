<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\SmsLog;

class SmsService
{

    public function __construct()
    {
        
    }

    public function sendSms($msisdn, $message)
    {
        $encoded_sms = rawurlencode($message);

        $smsApiUrl = "http://188.64.189.35:23013/cgi-bin/sendsms?username=Green_mega&password=123456&to={$msisdn}&text={$encoded_sms}&from=15099&dlr-mask=0&priority=3";

        $response = Http::get($smsApiUrl);

        $success = $response->successful();
        if ($success) {
            // Clean the message before logging
            $cleanMessage = preg_replace('/\s+/', ' ', trim($message));
            \App\Models\SmsLog::create([
                'msisdn' => $msisdn,
                'message' => $cleanMessage
            ]);
        }
        return $success;
    }
}