<?php

require __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\YasPaymentService;

// Accept parameters from CLI arguments or use defaults
$msisdn = $argv[1] ?? '255656692469';
$amount = $argv[2] ?? 300.00;
$boxType = $argv[3] ?? 'bronze';
$paymentType = $argv[4] ?? 'airtime';

// Generate a unique request ID
$requestId = "MYBXR" . date("YmdHis") . $msisdn;

echo "=== Charging Test ===\n";
echo "MSISDN: $msisdn\n";
echo "Amount: $amount\n";
echo "Box Type: $boxType\n";
echo "Payment Type: $paymentType\n";
echo "Request ID: $requestId\n";
echo "========================\n\n";

try {
    $yasPaymentService = new YasPaymentService();
    
    echo "Sending charge request...\n";
    
    $result = $yasPaymentService->sendChargeRequest(
        $requestId,
        $msisdn,
        $amount
    );
    
    echo "\n=== Charge Request Result ===\n";
    echo "Status: " . $result['status'] . "\n";
    echo "Message: " . $result['message'] . "\n";
    
    if (isset($result['reference_id'])) {
        echo "Reference ID: " . $result['reference_id'] . "\n";
    }
    
    if (isset($result['data'])) {
        echo "Response Data:\n";
        echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }
    
    if (isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n";
    }
    
    echo "============================\n";
    
    if ($result['status'] === 'success') {
        echo "\n✅ Charge request sent successfully!\n";
    } else {
        echo "\n❌ Charge request failed!\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";