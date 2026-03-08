# Local Development Setup (WordPress + MySQL + phpMyAdmin)

This project now includes a Docker-based local stack under `wordpress/`.

## Prerequisites

- Docker Desktop installed and running

## One-time setup

```bash
cd "/Users/nhayes/Documents/Barebones Apparel/wordpress"
cp .env.local.example .env.local
cp public_html/ops/config.local.php.example public_html/ops/config.local.php
```

## Start local services

```bash
./scripts/start-local.sh
```

- WordPress: `http://localhost:8080`
- phpMyAdmin: `http://localhost:8081`

## Import the SQL dump

```bash
./scripts/import-db.sh
```

By default this imports:

`wordpress/u632291655_barebones_ops.sql`

You can pass another SQL path:

```bash
./scripts/import-db.sh /absolute/path/to/dump.sql
```

## Stop local services

```bash
./scripts/stop-local.sh
```

## Notes

- `wp-config.php` now supports local DB overrides via environment variables (`WP_DB_NAME`, `WP_DB_USER`, `WP_DB_PASSWORD`, `WP_DB_HOST`).
- Optional: create `wordpress/public_html/wp-config-local.php` for local-only overrides; this file is git-ignored.
- Optional: update `wordpress/public_html/ops/config.local.php` with local ops DB creds; this file is git-ignored.
- Ops PHP errors are centralized in `wordpress/public_html/ops/bootstrap.php`.
- Set `OPS_DEBUG=1` to show errors in-browser during development (default is off).
- Set `OPS_ERROR_LOG=/absolute/path/to/log.log` to override log destination (default is `ops/runtime/php-error.log`).
- The import script updates `wp_options.home` and `wp_options.siteurl` to `http://localhost:8080`.
- If your DB table prefix is not `wp_`, update `scripts/import-db.sh`.
