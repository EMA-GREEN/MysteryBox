# Mystery Box Laravel — database migrations (schema for Go `mystrybox-backend`)

This app owns **all MySQL schema**. The Go API/workers do **not** ship SQL migrations; they expect tables created here.

**Migration files:** `database/migrations/` (**87** PHP files, chronological — run in filename order via Artisan).

## Core tables created by migrations

| Table(s) | Migration file |
|----------|----------------|
| `subscribers` | `2024-03-21-000000_create_subscribers_table.php` |
| `users`, `personal_access_tokens` | `2025_03_27_000001_create_users_table.php`, `2025_03_27_000010_create_personal_access_tokens_table.php` |
| `mystery_boxes` | `2025_03_27_000002_create_mystery_boxes_table.php` |
| `payments` | `2025_03_27_000003_create_payments_table.php` |
| **`payment_callbacks`** | **`2025_03_27_000004_create_payment_callbacks_table.php`** |
| `payment_notifications` | `2025_03_27_000005_create_payment_notifications_table.php` |
| `mystery_box_award_notifications` | `2025_03_27_000007_create_mystery_box_award_notifications_table.php` |
| `sms_notifications`, `sms_logs` | `2025_03_27_000008_*`, `2025_03_27_000009_*` |
| `app_notifications` | `2025_03_27_000013_create_app_notifications_table.php` |
| Disbursements + notifications | `2024_03_21_*`, `2024_03_27_*`, `2025_03_27_000019_*`, `2025_03_27_000020_*` |
| `airtime_disbursement_callbacks`, `wallet_disbursement_callbacks` | `2025_08_16_000001_*`, `2025_08_16_000002_*` |
| `bill_payment_notifications` → renamed to **`mobile_money_transaction_notifications`** | `2025_07_23_000001_*`, `2025_07_23_000006_*` (+ later alters) |
| `cache`, `cache_locks` | `2025_07_23_171305_create_cache_table.php` |
| `otps` | `2025_06_26_000001_create_otps_table.php` |
| `winners`, prizes, jackpots | `2025_06_10_*`, `2025_08_07_*`, `2025_08_11_*` |
| **`renewal_subscribers`**, **`renewal_payments`**, **`renewal_payment_callbacks`**, **`renewal_content_notifications`** | `2025_09_25_*`, `2025_09_23_000001_create_renewal_payment_callbacks_table.php` |
| `unsubscribers` | `2025_12_11_000000_create_unsubscribers_table.php` |
| `admins` → **`staff`** (rename) | `2026_01_30_000000_rename_admins_to_staff_table.php` (requires existing `admins` from legacy/prod) |
| M-Push / YAS free box | `2026_08_10_*`, `2026_09_03_*`, `2026_09_16_*` |
| `user_access_token` | `2023-10-01-000000_create_user_access_token_table.php` |

Many other files are **indexes**, column adds, and data fixes — still required for a DB that matches production.

**Note:** Dated shard tables (`payments_YYYYMMDD`, etc.) are created by **runtime jobs**, not these migrations. For lab/Go deploy, `payments` + outbox tables are enough unless you test sharding.

## GitHub

Canonical remote (same migration tree as this folder):

```text
https://github.com/EMA-GREEN/MysteryBox.git
```

## On the Go deploy host (e.g. `mysterybox-backend` / `10.4.9.82`)

MySQL runs in Docker on that host; use the compose network or publish port `3306` and credentials from `/home/ubuntu/mysterybox-deploy/.env`.

### One-time: clone Laravel app (migrations only need PHP + Composer)

```bash
sudo mkdir -p /opt/mysterybox-laravel
sudo chown "$USER":"$USER" /opt/mysterybox-laravel
git clone https://github.com/EMA-GREEN/MysteryBox.git /opt/mysterybox-laravel
cd /opt/mysterybox-laravel
```

### Configure `.env` for migrate (match Go `mysterybox.env` DB_*)

```bash
cp .env.example .env
# Edit DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# Example when MySQL is the compose service on the same host:
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_DATABASE=mysterybox
php artisan key:generate
```

Install PHP dependencies (PHP 8.2+, extensions: pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, bcmath):

```bash
composer install --no-dev --optimize-autoloader
```

### Apply all pending migrations

```bash
php artisan migrate --force
php artisan migrate:status
```

Verify callback table:

```bash
mysql -h127.0.0.1 -umysterybox -p mysterybox -e "SHOW TABLES LIKE 'payment_callbacks';"
```

### After schema exists

- Go **`POST /api/payments/payment-callback`** on `:8081` can insert into `payment_callbacks`.
- Start Go workers that publish/consume callback outbox rows as needed.

### Troubleshooting

| Issue | Action |
|-------|--------|
| `admins` missing before `2026_01_30` rename | Skip that migration on fresh lab DB or create minimal `admins` from prod DDL |
| Duplicate `winners` create migrations | Run full chain once; do not cherry-pick single files |
| Migration already applied on partial DB | `php artisan migrate:status` then `migrate --force` applies only pending |
| Prefer prod-identical schema | `mysqldump --no-data` from production instead of migrate |

## Local source path (Windows)

Authoritative copy used for this inventory:

`C:\Users\Emanuel\Documents\PROJECTS\MysteryBox20250912\MysteryBox`
