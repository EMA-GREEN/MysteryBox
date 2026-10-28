# Apache Deployment Guide for MysteryBox

## Problem
`php artisan serve` is single-threaded and causes intermittent connection issues. Apache provides proper multi-threaded handling.

## Steps to Deploy

### 1. Copy Apache Configuration
```bash
sudo cp apache/mysterybox.conf /etc/httpd/conf/mysterybox.conf
```

### 2. Verify Apache Configuration
```bash
sudo httpd -t -f /etc/httpd/conf/mysterybox.conf
```

### 3. Update Supervisor Configuration
```bash
sudo cp supervisor/mysterybox.ini /etc/supervisor/conf.d/mysterybox.ini
```

### 4. Reload Supervisor
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart mysterybox
```

### 5. Verify It's Running
```bash
# Check supervisor status
sudo supervisorctl status mysterybox

# Check if Apache is listening on port 8285
sudo netstat -tlnp | grep 8285
# Should show: tcp 0 0 0.0.0.0:8285 LISTEN <pid>/httpd

# Test connection
curl -v http://188.64.189.38:8285/api/
```

### 6. Monitor Logs
```bash
# Supervisor logs
tail -f /var/log/httpd/mysterybox.log

# Apache error logs
tail -f /var/log/httpd/mysterybox_error.log

# Apache access logs
tail -f /var/log/httpd/mysterybox_access.log
```

## Troubleshooting

### If Apache won't start:
1. Check if port 8285 is already in use:
   ```bash
   sudo lsof -i :8285
   ```

2. Check Apache syntax:
   ```bash
   sudo httpd -t -f /etc/httpd/conf/mysterybox.conf
   ```

3. Check file permissions:
   ```bash
   sudo chown -R apache:apache /var/www/html/MysteryBox
   sudo chmod -R 755 /var/www/html/MysteryBox
   sudo chmod -R 775 /var/www/html/MysteryBox/storage
   sudo chmod -R 775 /var/www/html/MysteryBox/bootstrap/cache
   ```

### If you need to revert to artisan serve:
1. Edit `/etc/supervisor/conf.d/mysterybox.ini`
2. Comment out the Apache line and uncomment the artisan serve line
3. Run: `sudo supervisorctl update && sudo supervisorctl restart mysterybox`

## Performance Tuning

The Apache config includes:
- `MaxRequestWorkers 256` - Maximum concurrent requests
- `ThreadsPerChild 25` - Threads per worker process
- `ServerLimit 16` - Maximum server processes

Adjust these based on your server's RAM and CPU.

