#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f ".env.local" ]]; then
  echo "Missing .env.local. Copy .env.local.example first:"
  echo "  cp .env.local.example .env.local"
  exit 1
fi

docker compose up -d db wordpress phpmyadmin

echo "Local services started."
echo "WordPress:  http://localhost:8080"
echo "phpMyAdmin: http://localhost:8081"
