# Mobile Money Load Test Guide

This guide explains how to run the Mobile Money Load Tests to validate your system's ability to handle 300 TPS (Transactions Per Second).

## Prerequisites

Before running the load tests, ensure you have:

### 1. System Requirements
- **PHP 8.1+** with required extensions
- **Redis** server running
- **MySQL/Database** accessible
- **Composer** dependencies installed
- **Sufficient system resources** (CPU, Memory, Network)

### 2. Application Setup
```bash
# Install dependencies
composer install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Clear caches
php artisan config:clear
php artisan cache:clear
```

### 3. Queue Configuration
Make sure your `.env` file has proper queue configuration:
```env
QUEUE_CONNECTION=redis_mobile_money
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 4. Start Required Services

#### Start Laravel Application
```bash
php artisan serve --port=8285
# Application will run at http://localhost:8285
```

#### Start Queue Workers (Important!)
```bash
# Start high-priority queue worker
php artisan queue:work redis_mobile_money --queue=mobile_money_high_priority --tries=3 --timeout=60

# In another terminal, start medium-priority worker
php artisan queue:work redis_mobile_money_medium --queue=mobile_money_medium_priority --tries=3 --timeout=120

# In another terminal, start low-priority worker  
php artisan queue:work redis_mobile_money_low --queue=mobile_money_low_priority --tries=3 --timeout=180
```

## Running Load Tests

### Method 1: Using the Load Test Runner (Recommended)

```bash
php run_load_tests.php
```

This interactive script will:
1. Check if your application is running
2. Show available tests
3. Let you select which test to run
4. Temporarily enable the selected test
5. Run the test and show results
6. Restore the test file to original state

### Method 2: Using PHPUnit Directly

#### Step 1: Enable Tests
Edit `tests/LoadTest/MobileMoneyLoadTest.php` and comment out the skip lines:
```php
// $this->markTestSkipped('Load test - run manually only');
```

#### Step 2: Run Specific Tests
```bash
# Run 300 TPS sustained load test (60 seconds)
vendor/bin/phpunit tests/LoadTest/MobileMoneyLoadTest.php --filter test_300_tps_sustained_load --no-coverage

# Run burst load test (500 TPS for 10 seconds)
vendor/bin/phpunit tests/LoadTest/MobileMoneyLoadTest.php --filter test_burst_load_handling --no-coverage

# Run queue processing test
vendor/bin/phpunit tests/LoadTest/MobileMoneyLoadTest.php --filter test_queue_processing_performance --no-coverage

# Run all load tests
vendor/bin/phpunit tests/LoadTest/MobileMoneyLoadTest.php --no-coverage
```

## Available Tests

### 1. 300 TPS Sustained Load Test
- **Duration**: 60 seconds
- **Target TPS**: 300
- **Total Requests**: 18,000
- **Purpose**: Validates sustained high-load performance

**Expected Results**:
- Success Rate: >99%
- Average Response Time: <50ms
- TPS Achieved: 295-305

### 2. Burst Load Test
- **Duration**: 10 seconds
- **Target TPS**: 500
- **Total Requests**: 5,000
- **Purpose**: Tests burst handling capability

**Expected Results**:
- Success Rate: >98%
- Average Response Time: <100ms
- System recovers quickly after burst

### 3. Queue Processing Performance Test
- **Jobs**: 1,000 notifications
- **Purpose**: Tests asynchronous processing performance
- **Measures**: Queue processing speed, job completion rate

**Expected Results**:
- All jobs processed successfully
- Processing rate: >100 jobs/second
- No failed jobs

## Monitoring During Tests

### 1. System Resources
```bash
# Monitor CPU and Memory
htop

# Monitor Redis
redis-cli monitor

# Monitor MySQL connections
SHOW PROCESSLIST;
```

### 2. Application Logs
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Monitor queue processing
php artisan queue:monitor
```

### 3. Queue Status
```bash
# Check queue sizes
php artisan queue:monitor redis_mobile_money

# Monitor mobile money queues specifically
php artisan monitor:mobile-money-queue
```

## Interpreting Results

### Success Criteria
- **TPS**: Should achieve target TPS ±5%
- **Success Rate**: >99% for sustained load, >98% for burst
- **Response Time**: <50ms average for API responses
- **Queue Processing**: All jobs processed without failures
- **System Stability**: No memory leaks or crashes

### Common Issues and Solutions

#### Low TPS Achievement
- **Cause**: Insufficient system resources
- **Solution**: Increase CPU/Memory, optimize database queries

#### High Error Rate
- **Cause**: Database connection limits, timeout issues
- **Solution**: Increase connection pool, optimize timeouts

#### Slow Response Times
- **Cause**: Database bottlenecks, inefficient code
- **Solution**: Add database indexes, optimize queries

#### Queue Backlog
- **Cause**: Insufficient queue workers
- **Solution**: Start more workers, optimize job processing

## Performance Tuning Tips

### 1. Database Optimization
```sql
-- Add indexes for mobile money transactions
CREATE INDEX idx_mobile_money_reference ON mobile_money_transactions(reference);
CREATE INDEX idx_mobile_money_status ON mobile_money_transactions(status);
CREATE INDEX idx_mobile_money_created_at ON mobile_money_transactions(created_at);
```

### 2. Redis Configuration
```redis
# Increase memory limit
maxmemory 2gb
maxmemory-policy allkeys-lru

# Optimize for performance
save ""
appendonly no
```

### 3. PHP-FPM Configuration
```ini
; Increase worker processes
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 15
```

## Troubleshooting

### Test Fails to Start
1. Check if application is running: `curl http://localhost:8285`
2. Verify Redis is running: `redis-cli ping`
3. Check database connection: `php artisan tinker` → `DB::connection()->getPdo()`

### Low Performance
1. Monitor system resources during test
2. Check for database slow queries
3. Verify queue workers are running
4. Review application logs for errors

### Memory Issues
1. Increase PHP memory limit: `memory_limit = 512M`
2. Monitor for memory leaks in application code
3. Restart queue workers periodically

## Security Considerations

- **Never run load tests against production**
- Use test/staging environment only
- Monitor system resources to prevent overload
- Have monitoring and alerting in place

## Next Steps

After successful load testing:
1. Deploy optimized configuration to staging
2. Run acceptance tests
3. Set up production monitoring
4. Plan gradual traffic increase
5. Implement auto-scaling if needed

---

For questions or issues, refer to the deployment guide: `mobile-money-300tps-deployment.md`