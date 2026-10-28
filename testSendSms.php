<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\SmsService;

// Accept MSISDN and message from CLI arguments or use defaults
$msisdn = $argv[1] ?? '255656692469';
$message = $argv[2] ?? 'Test SMS from testSendSms.php';

$smsService = new SmsService();

$result = $smsService->sendSms($msisdn, $message);

if ($result) {
    echo "SMS sent successfully to $msisdn\n";
} else {
    echo "Failed to send SMS to $msisdn\n";
} 