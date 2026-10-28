<?php

namespace App\Http\Controllers;

use App\Services\SmsService;
use Illuminate\Http\Request;
use App\Models\SmsNotification;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    public function receiveSms(Request $request)
    {
        // Log the incoming request first
        Log::info('Received SMS request', [
            'all_params' => $request->all()
        ]);

        $msisdn = $request->input('sender');
        $msisdn = str_replace("+", "", $msisdn);
        $message = $request->input('message');
        $keyword = $request->input('keyword');
        $msg_id = $request->input('id');

        $rowtext = $keyword . " " . $message;
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $rowtext);

        // Store incoming SMS notification
        SmsNotification::create([
            'msisdn' => $msisdn,
            'message' => $text,
            'msg_id' => $msg_id,
            'publish_status' => false
        ]);

        return response("MO Received Successfully", 200)
                ->header('Content-Type', 'text/plain');
    }

    public function receiveMo(Request $request)
    {
        // Log the incoming MO request first
        Log::info('Received MO request', [
            'all_params' => $request->all(),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
            'text' => $request->input('text'),
            'smsc' => $request->input('smsc'),
            'charset' => $request->input('charset'),
            'coding' => $request->input('coding'),
            'udh' => $request->input('udh'),
            'sms_id' => $request->input('sms_id')
        ]);

        $msisdn = $request->input('from');
        $msisdn = str_replace("+", "", $msisdn);
        $message = $request->input('text');
        $to = $request->input('to');
        $smsc = $request->input('smsc');
        $sms_id = $request->input('sms_id');
        $charset = $request->input('charset', 'UTF-8');
        $coding = $request->input('coding', '0');

        // Convert message encoding if needed
        $text = iconv($charset, 'ASCII//TRANSLIT//IGNORE', $message);

        // Store incoming SMS notification
        SmsNotification::create([
            'msisdn' => $msisdn,
            'message' => $text,
            'msg_id' => $sms_id,
            'publish_status' => false
        ]);

        // Log successful processing
        Log::info('MO SMS processed successfully', [
            'msisdn' => $msisdn,
            'message_length' => strlen($text),
            'sms_id' => $sms_id
        ]);
        
        return response()->json(['message' => 'MO SMS received and processed']);
    }

    public function receiveDlr(Request $request)
    {
        $originator = $request->input('oa');
        $destination = $request->input('da');
        $smsc = $request->input('smsc');
        $dlr_type = $request->input('dlr_type');
        $smscid = $request->input('smscid');
        $ismsid = $request->input('ismsid');

        Log::info('Received DLR', [
            'originator' => $originator,
            'destination' => $destination,
            'smsc' => $smsc,
            'dlr_type' => $dlr_type,
            'smscid' => $smscid,
            'ismsid' => $ismsid
        ]);

        return response()->json(['message' => 'DLR received']);
    }
}
