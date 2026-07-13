#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_SQL_FILE="${1:-$ROOT_DIR/u632291655_CIS0T.sql}"
OPS_SQL_FILE="${2:-$ROOT_DIR/u632291655_barebones_ops.sql}"

if [[ ! -f "$WP_SQL_FILE" ]]; then
  echo "WordPress SQL file not found: $WP_SQL_FILE"
  exit 1
fi

if [[ ! -f "$OPS_SQL_FILE" ]]; then
  echo "Ops SQL file not found: $OPS_SQL_FILE"
  exit 1
fi

if [[ ! -f "$ROOT_DIR/.env.local" ]]; then
  echo "Missing .env.local. Copy .env.local.example first:"
  echo "  cp .env.local.example .env.local"
  exit 1
fi

# shellcheck source=/dev/null
source "$ROOT_DIR/.env.local"

OPS_DB_NAME="${OPS_DB_NAME:-barebones_ops}"

cd "$ROOT_DIR"
docker compose up -d db

DB_ADMIN_CMD="mysqladmin"
DB_CLIENT_CMD="mysql"
if docker compose exec -T db sh -lc 'command -v mariadb-admin >/dev/null 2>&1'; then
  DB_ADMIN_CMD="mariadb-admin"
fi
if docker compose exec -T db sh -lc 'command -v mariadb >/dev/null 2>&1'; then
  DB_CLIENT_CMD="mariadb"
fi

echo "Waiting for MySQL..."
db_ready="false"
for _ in {1..60}; do
  if docker compose exec -T db "${DB_ADMIN_CMD}" ping -uroot "-p${MYSQL_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
    db_ready="true"
    break
  fi
  sleep 2
done

if [[ "$db_ready" != "true" ]]; then
  echo "MySQL did not become ready in time. Check logs with:"
  echo "  docker compose logs db"
  exit 1
fi

echo "Resetting local databases..."
docker compose exec -T db "${DB_CLIENT_CMD}" -uroot "-p${MYSQL_ROOT_PASSWORD}" -e "DROP DATABASE IF EXISTS \`${MYSQL_DATABASE}\`; CREATE DATABASE \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
docker compose exec -T db "${DB_CLIENT_CMD}" -uroot "-p${MYSQL_ROOT_PASSWORD}" -e "DROP DATABASE IF EXISTS \`${OPS_DB_NAME}\`; CREATE DATABASE \`${OPS_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Importing WordPress SQL dump into ${MYSQL_DATABASE}..."
docker compose exec -T db "${DB_CLIENT_CMD}" -uroot "-p${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" < "$WP_SQL_FILE"

echo "Importing Ops SQL dump into ${OPS_DB_NAME}..."
docker compose exec -T db "${DB_CLIENT_CMD}" -uroot "-p${MYSQL_ROOT_PASSWORD}" "${OPS_DB_NAME}" < "$OPS_SQL_FILE"

options_table="$(
  docker compose exec -T db "${DB_CLIENT_CMD}" -N -B -uroot "-p${MYSQL_ROOT_PASSWORD}" \
    -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${MYSQL_DATABASE}' AND table_name LIKE '%\\_options' LIMIT 1;"
)"

if [[ -n "$options_table" ]]; then
  echo "Setting WordPress home/siteurl to localhost using ${options_table}..."
  docker compose exec -T db "${DB_CLIENT_CMD}" -uroot "-p${MYSQL_ROOT_PASSWORD}" "${MYSQL_DATABASE}" \
    -e "UPDATE \`${options_table}\` SET option_value='http://localhost:8080' WHERE option_name IN ('home','siteurl');"
else
  echo "No WordPress *_options table found in ${MYSQL_DATABASE}."
fi

docker compose up -d wordpress phpmyadmin

echo "Import complete."
echo "WordPress:  http://localhost:8080"
echo "phpMyAdmin: http://localhost:8081"
echo "Ops login:   http://localhost:8080/ops/login.php"
echo "If your WordPress table prefix is not wp_, edit scripts/import-db.sh and change the *_options lookup."
