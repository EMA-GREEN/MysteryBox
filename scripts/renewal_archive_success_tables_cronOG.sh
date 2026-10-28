#!/usr/bin/env bash
#
# Renewal archive: tables renewal_payments_yyyymmdd and renewal_payment_callbacks_yyyymmdd
# older than 7 days → keep only status='success', rename to *_success_yyyymmdd.
# Intended to run daily at 03:00 via cron on CentOS.
#
# CentOS crontab (3:00 daily):
#   0 3 * * * /var/www/html/MysteryBox/scripts/renewal_archive_success_tables_cron.sh >> /var/log/mysterybox-renewal-archive.log 2>&1
#
# Run manually with nohup (use full path; run from server, not Windows):
#   nohup bash /var/www/html/MysteryBox/scripts/renewal_archive_success_tables_cron.sh >> /var/log/mysterybox-renewal-archive.log 2>&1 &
# Or from app dir:
#   nohup bash scripts/renewal_archive_success_tables_cron.sh >> /var/log/mysterybox-renewal-archive.log 2>&1 &
#
# Ensure script is executable: chmod +x scripts/renewal_archive_success_tables_cron.sh
# Ensure log exists and is writable: touch /var/log/mysterybox-renewal-archive.log

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR"

# Use php from PATH (on CentOS usually /usr/bin/php)
php artisan mysterybox:renewal:archive-success-tables --days=7