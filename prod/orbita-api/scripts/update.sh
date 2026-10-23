#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/compose.sh"
python3 scripts/check-production.py --env-file .env
"${COMPOSE[@]}" pull
"${COMPOSE[@]}" up -d --wait redis
"${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:catalog-inspect --no-ansi | python3 scripts/validate-schema-report.py
"${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:migrations-check --no-ansi
"${COMPOSE[@]}" up -d --force-recreate
"${COMPOSE[@]}" ps
