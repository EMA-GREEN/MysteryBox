#!/usr/bin/env bash
set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR"

# Use php from PATH (on CentOS usually /usr/bin/php)
php artisan mysterybox:renewal:archive-success-tables --days=7