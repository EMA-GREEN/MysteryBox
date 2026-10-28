# Draw Winners Export Script

This script exports top N winners for daily, weekly, and monthly draws to CSV files.

## Configuration

Edit `config/draws.php` to configure:
- **Top N winners** per draw period (daily, weekly, monthly)
- **Ranking criteria** (currently: `prize_value` - descending)
- **CSV export settings** (directory, filename format, etc.)

### Example Configuration

```php
'winners' => [
    'daily' => [
        'top_n' => 10, // Top 10 winners
        'ranking_criteria' => 'prize_value',
    ],
    'weekly' => [
        'top_n' => 20, // Top 20 winners
        'ranking_criteria' => 'prize_value',
    ],
    'monthly' => [
        'top_n' => 50, // Top 50 winners
        'ranking_criteria' => 'prize_value',
    ],
],
```

## Usage

### Manual Execution

Export all draw periods:
```bash
php artisan winners:export-draws
```

Export specific period:
```bash
php artisan winners:export-draws --period=daily
php artisan winners:export-draws --period=weekly
php artisan winners:export-draws --period=monthly
```

### Cron Job Setup

To run automatically at 00:00 (midnight) every day, add to your crontab:

```bash
crontab -e
```

**Option 1: Basic (no logging)**
```
0 0 * * * cd /var/www/html/MysteryBox && php artisan winners:export-draws >> /dev/null 2>&1
```

**Option 2: With logging (recommended)**
```
0 0 * * * cd /var/www/html/MysteryBox && php artisan winners:export-draws >> /var/www/html/MysteryBox/storage/logs/draw-winners-export.log 2>&1
```

**Option 3: With date-based log file**
```
0 0 * * * cd /var/www/html/MysteryBox && php artisan winners:export-draws >> /var/www/html/MysteryBox/storage/logs/draw-winners-export-$(date +\%Y-\%m-\%d).log 2>&1
```

**Option 4: With full path to PHP (if PHP is not in PATH)**
```
0 0 * * * cd /var/www/html/MysteryBox && /usr/bin/php artisan winners:export-draws >> /var/www/html/MysteryBox/storage/logs/draw-winners-export.log 2>&1
```

**Cron Format Explanation:**
- `0 0 * * *` = Run at 00:00 (midnight) every day
- `cd /var/www/html/MysteryBox` = Change to app directory
- `php artisan winners:export-draws` = Run the command
- `>> /path/to/log.log 2>&1` = Redirect output and errors to log file

**To verify cron is working:**
```bash
# Check cron logs
tail -f /var/log/cron

# Or check your application logs
tail -f /var/www/html/MysteryBox/storage/logs/draw-winners-export.log
```

**For Windows (Task Scheduler):**
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily at 00:00
4. Action: Start a program
5. Program: `php` (or full path: `C:\php\php.exe`)
6. Arguments: `artisan winners:export-draws`
7. Start in: `C:\var\www\html\MysteryBox`

## Output

CSV files are saved to: `storage/app/winners/`

Filename format: `{draw_period}_winners_{date}.csv`

Examples:
- `daily_winners_2025-12-11.csv`
- `weekly_winners_2025-12-11.csv`
- `monthly_winners_2025-12-11.csv`

## CSV Format

The CSV files include the following columns:
- Rank
- MSISDN
- Winner Type
- Prize Description
- Prize Value (TZS)
- Box Type
- Draw Period
- Won At
- Disbursement Status
- Source Table
- Source ID

## Date Ranges

- **Daily**: Winners from the current day (00:00:00 to 23:59:59)
- **Weekly**: Winners from the current week (Monday to Sunday)
- **Monthly**: Winners from the current month (1st to last day)

## Notes

- The script automatically creates the `storage/app/winners/` directory if it doesn't exist
- Winners are ranked by `prize_value` in descending order (highest first)
- Only the top N configured winners are exported
- The script logs all operations for debugging

