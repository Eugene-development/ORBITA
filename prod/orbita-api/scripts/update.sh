#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/compose.sh"
"${COMPOSE[@]}" pull
"${COMPOSE[@]}" up -d --wait redis
"${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:catalog-inspect --check --no-ansi
"${COMPOSE[@]}" run --rm --no-deps app php artisan orbita:migrations-check --no-ansi
"${COMPOSE[@]}" up -d --force-recreate
"${COMPOSE[@]}" ps
