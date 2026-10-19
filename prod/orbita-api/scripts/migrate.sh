#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/compose.sh"
python3 scripts/check-production.py --env-file .env
python3 scripts/check-migration-settings.py
"${COMPOSE[@]}" --profile tools pull
"${COMPOSE[@]}" up -d --wait redis
before=$("${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:catalog-inspect --no-ansi | python3 scripts/validate-schema-report.py --fingerprint)
"${COMPOSE[@]}" --profile tools run --rm db
# The migration image contains only canonical orbita_* migrations at /migrations.
after=$("${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:catalog-inspect --no-ansi | python3 scripts/validate-schema-report.py --fingerprint)
[[ "$before" == "$after" ]] || { echo 'Catalog fingerprint changed; stop release.' >&2; exit 1; }
echo 'Service migrations complete; catalog fingerprint unchanged.'
