#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/check-migrations.py
case "${1:-}" in
  local) docker compose --profile tools run --rm db ;;
  production) exec bash prod/orbita-api/scripts/migrate.sh ;;
  *) echo 'Usage: migrate-services.sh local|production' >&2; exit 2 ;;
esac
