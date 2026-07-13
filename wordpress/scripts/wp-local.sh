#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WORDPRESS_DIR="$(cd "${SCRIPT_DIR}/../public_html" && pwd)"
ENV_FILE="${SCRIPT_DIR}/../.env.local"

if ! command -v php >/dev/null 2>&1; then
  echo "php is required but was not found on PATH." >&2
  exit 1
fi

if [ ! -f /usr/local/bin/wp ]; then
  echo "wp-cli phar was not found at /usr/local/bin/wp." >&2
  exit 1
fi

if [ -f "${ENV_FILE}" ]; then
  # shellcheck disable=SC2046
  export $(grep -v '^#' "${ENV_FILE}" | xargs)
fi

export WP_DB_NAME="${WP_DB_NAME:-${MYSQL_DATABASE:-barebones_wp}}"
export WP_DB_USER="${WP_DB_USER:-${MYSQL_USER:-wp_user}}"
export WP_DB_PASSWORD="${WP_DB_PASSWORD:-${MYSQL_PASSWORD:-wp_password}}"
export WP_DB_HOST="${WP_DB_HOST:-127.0.0.1:3307}"

exec php \
  -d memory_limit=512M \
  -d error_reporting=22527 \
  -d display_errors=0 \
  /usr/local/bin/wp \
  --path="${WORDPRESS_DIR}" \
  "$@"
