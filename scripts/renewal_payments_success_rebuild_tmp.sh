#!/bin/sh
# Note: keep this script portable across /bin/sh implementations.
set -e

# Rebuild all tables matching: renewal_payments_success_202%
# Steps per table:
# 1) create <table>_tmp (fixed schema)
# 2) insert into <table>_tmp select * from <table>
# 3) drop <table>
# 4) rename <table>_tmp to <table>

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Load DB_* from .env (simple parser; assumes no spaces around '=')
if [ -f "$APP_DIR/.env" ]; then
  while IFS='=' read -r key val; do
    case "$key" in
      DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)
        # strip surrounding quotes if present
        val=$(printf '%s' "$val" | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
        eval "$key=\$val"
        export "$key"
        ;;
    esac
  done < "$APP_DIR/.env"
fi

: "${DB_HOST:?Missing DB_HOST in .env}"
: "${DB_PORT:?Missing DB_PORT in .env}"
: "${DB_DATABASE:?Missing DB_DATABASE in .env}"
: "${DB_USERNAME:?Missing DB_USERNAME in .env}"
: "${DB_PASSWORD:?Missing DB_PASSWORD in .env}"

# Avoid putting password in argv (shows up in process list)
export MYSQL_PWD="$DB_PASSWORD"
MYSQL_CMD="mysql -h$DB_HOST -P$DB_PORT -u$DB_USERNAME $DB_DATABASE --batch --skip-column-names"

mysql_exec() {
  # Usage: mysql_exec "SQL..."
  # shellcheck disable=SC2086
  $MYSQL_CMD -e "$1"
}

TABLES="$(mysql_exec "SHOW TABLES LIKE 'renewal_payments_success_202%';")"
if [ -z "$TABLES" ]; then
  echo "No tables found matching renewal_payments_success_202% in ${DB_DATABASE}"
  exit 0
fi

for tbl in $TABLES; do
  tmp="${tbl}_tmp"

  echo "Processing: ${tbl}"

  # Safety: fail if tmp already exists
  exists_tmp="$(mysql_exec "SHOW TABLES LIKE '${tmp}';")"
  if [ -n "$exists_tmp" ]; then
    echo "ERROR: temp table already exists: ${tmp}"
    exit 1
  fi

  # STEP 2: create temp table with fixed schema
  create_sql=$(cat <<EOF
CREATE TABLE \`${tmp}\` (
  \`id\` bigint unsigned NOT NULL AUTO_INCREMENT,
  \`msisdn\` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  \`request_id\` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  \`payment_type\` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  \`amount\` decimal(10,2) NOT NULL,
  \`status\` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  \`ack_status_code\` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  \`ack_description\` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  \`callback_status_code\` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  \`callback_description\` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  \`ack_received_time\` timestamp NULL DEFAULT NULL,
  \`callback_received_time\` timestamp NULL DEFAULT NULL,
  \`created_at\` timestamp NULL DEFAULT NULL,
  \`updated_at\` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (\`id\`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
)
  mysql_exec "$create_sql"

  # STEP 3: copy data
  mysql_exec "INSERT INTO \`${tmp}\` SELECT * FROM \`${tbl}\`;"

  # STEP 4: drop original
  mysql_exec "DROP TABLE \`${tbl}\`;"

  # STEP 5: rename tmp to original name
  mysql_exec "RENAME TABLE \`${tmp}\` TO \`${tbl}\`;"

  echo "OK: ${tbl}"
done

echo "Done."

