#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/compose.sh"
python3 scripts/check-production.py --env-file .env
if [[ -f .env.migrate ]]; then python3 scripts/check-migration-settings.py; fi
"${COMPOSE[@]}" --profile tools pull
"${COMPOSE[@]}" up -d --wait redis
"${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:catalog-inspect --no-ansi | python3 scripts/validate-schema-report.py
"${COMPOSE[@]}" --profile tools run --rm --no-deps db php artisan migrate:status --path=/migrations --realpath --pending=1 --no-ansi
"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" ps
