# Mobile Money 300 TPS Deployment Configuration

## Overview
This configuration supports handling 300 transactions per second (TPS) for mobile money notifications with 99.9% uptime over 24 hours daily.

## Infrastructure Requirements

### Server Specifications (Minimum)
- **CPU**: 8 cores (16 threads) @ 3.0GHz+
- **RAM**: 32GB DDR4
- **Storage**: NVMe SSD with 10,000+ IOPS
- **Network**: 1Gbps+ bandwidth, <10ms latency

### Database Configuration (MySQL/PostgreSQL)
```sql
-- Connection pool settings
max_connections = 500
innodb_buffer_pool_size = 16G
innodb_log_file_size = 2G
innodb_flush_log_at_trx_commit = 2
query_cache_size = 256M
```

### Redis Configuration
```redis
# Memory optimization
maxmemory 8gb
maxmemory-policy allkeys-lru

# Performance tuning
tcp-keepalive 60
timeout 300
tcp-backlog 511

# Persistence (for queue reliability)
save 900 1
save 300 10
save 60 10000
```

## Laravel Configuration

### Environment Variables (.env)
```env
# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default

# Database Connection Pool
DB_CONNECTION=mysql
DB_POOL_MIN=10
DB_POOL_MAX=100

# Cache Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=info
```

### PHP-FPM Configuration
```ini
; Process management
pm = dynamic
pm.max_children = 100
pm.start_servers = 20
pm.min_spare_servers = 10
pm.max_spare_servers = 30
pm.max_requests = 1000

; Performance tuning
request_terminate_timeout = 30s
rlimit_files = 65536
rlimit_core = unlimited
```

### Nginx Configuration
```nginx
upstream laravel_backend {
    server 127.0.0.1:9000 max_fails=3 fail_timeout=30s;
    server 127.0.0.1:9001 max_fails=3 fail_timeout=30s;
    server 127.0.0.1:9002 max_fails=3 fail_timeout=30s;
    server 127.0.0.1:9003 max_fails=3 fail_timeout=30s;
}

server {
    listen 80;
    
    # Connection limits
    limit_conn_zone $binary_remote_addr zone=conn_limit_per_ip:10m;
    limit_req_zone $binary_remote_addr zone=req_limit_per_ip:10m rate=100r/s;
    
    location /api/mobile-money/notification {
        limit_conn conn_limit_per_ip 20;
        limit_req zone=req_limit_per_ip burst=50 nodelay;
        
        fastcgi_pass laravel_backend;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
        
        # Timeouts
        fastcgi_connect_timeout 5s;
        fastcgi_send_timeout 10s;
        fastcgi_read_timeout 10s;
    }
}
```

## Queue Worker Configuration

### Supervisor Configuration (/etc/supervisor/conf.d/mobile-money-workers.conf)
```ini
[program:mobile-money-high-priority]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis_mobile_money --queue=mobile_money_high_priority --sleep=0 --tries=3 --max-time=3600 --memory=512
directory=/path/to/project
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/var/log/mobile-money-high-priority.log
stopwaitsecs=3600

[program:mobile-money-medium-priority]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis_mobile_money_medium --queue=mobile_money_medium_priority --sleep=1 --tries=3 --max-time=3600 --memory=512
directory=/path/to/project
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/mobile-money-medium-priority.log
stopwaitsecs=3600

[program:mobile-money-low-priority]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis_mobile_money_low --queue=mobile_money_low_priority --sleep=3 --tries=3 --max-time=3600 --memory=512
directory=/path/to/project
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/mobile-money-low-priority.log
stopwaitsecs=3600
```

## Monitoring and Alerting

### Performance Monitoring Commands
```bash
# Start queue monitoring
php artisan monitor:mobile-money-queue --interval=10

# Check queue status
php artisan queue:monitor redis_mobile_money:mobile_money_high_priority,redis_mobile_money_medium:mobile_money_medium_priority,redis_mobile_money_low:mobile_money_low_priority --max=100

# Monitor failed jobs
php artisan queue:failed
```

### Health Check Endpoint
Create a health check endpoint that verifies:
- Database connectivity
- Redis connectivity
- Queue worker status
- Recent processing rate

### Alerting Thresholds
- **Queue Backlog**: Alert if >1000 pending jobs
- **Failed Jobs**: Alert if >50 failures per hour
- **Response Time**: Alert if >100ms average
- **TPS**: Alert if <250 or >350 TPS
- **Memory Usage**: Alert if >80% usage
- **CPU Usage**: Alert if >85% usage

## Deployment Steps

1. **Infrastructure Setup**
   ```bash
   # Install dependencies
   sudo apt update
   sudo apt install nginx php8.1-fpm redis-server mysql-server supervisor
   
   # Configure services
   sudo systemctl enable nginx php8.1-fpm redis-server mysql supervisor
   ```

2. **Application Deployment**
   ```bash
   # Deploy code
   git clone <repository>
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   
   # Database setup
   php artisan migrate
   php artisan queue:table
   php artisan queue:failed-table
   ```

3. **Queue Workers**
   ```bash
   # Start supervisor
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start mobile-money-high-priority:*
   sudo supervisorctl start mobile-money-medium-priority:*
   sudo supervisorctl start mobile-money-low-priority:*
   ```

4. **Monitoring Setup**
   ```bash
   # Start monitoring in background
   nohup php artisan monitor:mobile-money-queue --interval=30 > /var/log/queue-monitor.log 2>&1 &
   ```

## Performance Expectations

### Target Metrics
- **Throughput**: 300+ TPS sustained
- **Response Time**: <50ms for webhook endpoint
- **Queue Processing**: <2s average job processing time
- **Uptime**: 99.9% (8.76 hours downtime per year)
- **Error Rate**: <0.1%

### Capacity Planning
- **Peak Load**: 400 TPS (33% overhead)
- **Queue Capacity**: 10,000+ jobs in memory
- **Storage Growth**: ~50GB per month (estimated)
- **Bandwidth**: 100Mbps sustained

## Troubleshooting

### Common Issues
1. **High Queue Backlog**: Scale up workers or optimize job processing
2. **Memory Leaks**: Restart workers regularly (max-time=3600)
3. **Database Locks**: Optimize queries and use proper indexing
4. **Redis Memory**: Monitor and adjust maxmemory settings

### Emergency Procedures
1. **Scale Workers**: Increase numprocs in supervisor config
2. **Failover**: Switch to database queue if Redis fails
3. **Rate Limiting**: Implement circuit breaker pattern
4. **Data Recovery**: Use failed_jobs table for reprocessing

## Security Considerations

1. **Input Validation**: Strict validation on all webhook data
2. **Rate Limiting**: Prevent abuse with nginx limits
3. **Authentication**: Verify webhook signatures
4. **Logging**: Comprehensive audit trail
5. **Encryption**: TLS for all external communications