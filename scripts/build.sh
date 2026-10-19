#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
services=(api db front admin)
selected_service=""
push=true
for argument in "$@"; do
 case "$argument" in
  local) exec docker compose build api front admin ;;
  --push) push=true ;;
  --no-push) push=false ;;
  api|db|front|admin)
   [[ -z "$selected_service" ]] || { echo 'Specify one service.' >&2; exit 1; }
   selected_service="$argument" ;;
  *) echo "Usage: $0 [api|db|front|admin|local] [--push|--no-push] (push by default)" >&2; exit 1 ;;
 esac
done
if [[ -n "$selected_service" ]]; then services=("$selected_service"); fi
python3 scripts/check-migrations.py
api_built=false
build_api() {
 docker build --platform "${PLATFORM:-linux/amd64}" --target production -t larux/orbita-api:latest -f ms/orbita-api/Dockerfile.production ms/orbita-api
 api_built=true
}
for service in "${services[@]}"; do
 image="larux/orbita-$service:latest"
 case "$service" in
  api) build_api ;;
  db) if [[ "$api_built" != true ]]; then build_api; fi
      docker build --platform "${PLATFORM:-linux/amd64}" -t "$image" -f ms/orbita-db/Dockerfile.production ms/orbita-db ;;
  *) docker build --platform "${PLATFORM:-linux/amd64}" -t "$image" -f "ms/orbita-$service/Dockerfile.production" "ms/orbita-$service" ;;
 esac
 if [[ "$push" == true ]]; then docker push "$image"; fi
done
