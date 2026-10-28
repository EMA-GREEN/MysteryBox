#!/bin/bash

echo "Mobile Money Transaction Test - cURL with Simple Timing"
echo "======================================================"
echo ""

# Function to test with timing
test_with_timing() {
    local test_name="$1"
    local request_data="$2"
    
    echo "Test: $test_name"
    echo "Request Data:"
    echo "$request_data"
    echo ""
    
    # Measure time using curl with timing
    echo "Sending request..."
    start_time=$(date +%s)
    
    response=$(curl -s -w "\nHTTP_CODE:%{http_code}\nTIME_TOTAL:%{time_total}\nTIME_CONNECT:%{time_connect}\nTIME_STARTTRANSFER:%{time_starttransfer}\n" \
        -X POST http://188.64.189.38:8285/api/mobile-money/notification \
        -H "Content-Type: application/json" \
        -d "$request_data")
    
    end_time=$(date +%s)
    
    # Extract timing information
    http_code=$(echo "$response" | grep "HTTP_CODE:" | cut -d: -f2)
    time_total=$(echo "$response" | grep "TIME_TOTAL:" | cut -d: -f2)
    time_connect=$(echo "$response" | grep "TIME_CONNECT:" | cut -d: -f2)
    time_starttransfer=$(echo "$response" | grep "TIME_STARTTRANSFER:" | cut -d: -f2)
    
    # Extract response body (remove timing lines)
    response_body=$(echo "$response" | sed '/^HTTP_CODE:/d' | sed '/^TIME_/d')
    
    # Calculate total time using bash
    total_time=$((end_time - start_time))
    
    echo "Response Status: $http_code"
    echo "Response Body:"
    echo "$response_body"
    echo ""
    
    echo "Timing Information:"
    echo "-------------------"
    echo "Total Time (bash): ${total_time}s"
    echo "Total Time (curl): ${time_total}s"
    echo "Connect Time: ${time_connect}s"
    echo "Start Transfer Time: ${time_starttransfer}s"
    echo ""
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo "✅ SUCCESS: Request processed successfully"
    else
        echo "❌ FAILED: Request failed with status $http_code"
    fi
    
    echo ""
    echo "=================================================="
    echo ""
}

# Test 1: Water Bill Payment
test_with_timing "Water Bill Payment" '{
    "SourceCustomerMobile": "255656692469",
    "BrandName": "water",
    "OriginalAmount": 6000,
    "SalesOrderNumber": "BILL-INV-20250723-009",
    "SalesOrderStatus": "success",
    "TransactionDate": "2025-07-23 17:47"
}'

# Test 2: Electricity Bill Payment
test_with_timing "Electricity Bill Payment" '{
    "SourceCustomerMobile": "255712345678",
    "BrandName": "electricity",
    "OriginalAmount": 15000,
    "SalesOrderNumber": "BILL-INV-20250723-010",
    "SalesOrderStatus": "success",
    "TransactionDate": "2025-07-23 18:30"
}'

# Test 3: Failed Transaction
test_with_timing "Failed Transaction" '{
    "SourceCustomerMobile": "255798765432",
    "BrandName": "internet",
    "OriginalAmount": 25000,
    "SalesOrderNumber": "BILL-INV-20250723-011",
    "SalesOrderStatus": "failed",
    "TransactionDate": "2025-07-23 19:15"
}'

# Test 4: Pending Transaction
test_with_timing "Pending Transaction" '{
    "SourceCustomerMobile": "255745678901",
    "BrandName": "tv",
    "OriginalAmount": 8000,
    "SalesOrderNumber": "BILL-INV-20250723-012",
    "SalesOrderStatus": "pending",
    "TransactionDate": "2025-07-23 20:00"
}'

# Test 5: Large Amount Transaction
test_with_timing "Large Amount Transaction" '{
    "SourceCustomerMobile": "255789012345",
    "BrandName": "premium",
    "OriginalAmount": 100000,
    "SalesOrderNumber": "BILL-INV-20250723-013",
    "SalesOrderStatus": "success",
    "TransactionDate": "2025-07-23 21:00"
}'

# Test 6: Invalid Data (for error handling)
test_with_timing "Invalid Data Test" '{
    "SourceCustomerMobile": "",
    "BrandName": "invalid",
    "OriginalAmount": -100,
    "SalesOrderNumber": "",
    "SalesOrderStatus": "invalid_status",
    "TransactionDate": "invalid-date"
}'

echo "Performance Summary:"
echo "==================="
echo "All tests completed with timing measurements."
echo ""
echo "Timing Metrics Explained:"
echo "- Total Time (bash): Total time measured by bash script (seconds)"
echo "- Total Time (curl): Total time measured by curl (seconds)"
echo "- Connect Time: Time to establish connection (seconds)"
echo "- Start Transfer Time: Time to first byte received (seconds)"
echo ""
echo "Expected Response Format:"
echo "========================"
echo '{"status":"success","message":"Mobile money transaction notification received"}'
echo ""
echo "To check database after tests:"
echo "mysql> SELECT * FROM mobile_money_transaction_notifications ORDER BY created_at DESC LIMIT 10;"
