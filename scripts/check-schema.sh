#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ "${1:-local}" == production ]]; then
  docker compose --env-file prod/orbita-api/.env -f prod/orbita-api/docker-compose.yml exec -T app php artisan orbita:catalog-inspect
else
  docker compose exec -T api php artisan orbita:catalog-inspect
fi
