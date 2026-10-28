<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Payment;
use Illuminate\Support\Facades\DB;

echo "Testing direct database update for ack_received_time...\n";

try {
    // Get the latest payment
    $payment = Payment::latest()->first();
    if (!$payment) {
        echo "No payments found in database\n";
        exit(1);
    }
    
    echo "Testing with payment ID: {$payment->id}\n";
    echo "Current ack_received_time: " . ($payment->ack_received_time ?? 'NULL') . "\n";
    
    // Direct database update test
    $currentTime = now();
    echo "Attempting to update with timestamp: {$currentTime}\n";
    
    $directResult = DB::table('payments')
        ->where('id', $payment->id)
        ->update(['ack_received_time' => $currentTime]);
    
    echo "Direct DB update result: " . ($directResult ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Check the result directly from database
    $updatedPayment = DB::table('payments')->where('id', $payment->id)->first();
    echo "After direct update ack_received_time: " . ($updatedPayment->ack_received_time ?? 'NULL') . "\n";
    
    // Test Eloquent update
    $payment->refresh();
    echo "Eloquent model ack_received_time: " . ($payment->ack_received_time ?? 'NULL') . "\n";
    
    // Test Eloquent update method
    $eloquentResult = $payment->update(['ack_received_time' => now()]);
    echo "Eloquent update result: " . ($eloquentResult ? 'SUCCESS' : 'FAILED') . "\n";
    
    $payment->refresh();
    echo "After Eloquent update ack_received_time: " . ($payment->ack_received_time ?? 'NULL') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
