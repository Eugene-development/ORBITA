#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
unset COMPOSE_PROFILES COMPOSE_FILE COMPOSE_ENV_FILES
if docker compose version >/dev/null 2>&1; then
    COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE=(docker-compose)
else
    echo "Не найден Docker Compose" >&2
    exit 1
fi
if [[ ! -s .env ]]; then
    echo "Создайте .env рядом с docker-compose.yml и заполните настройки" >&2
    exit 1
fi
COMPOSE+=(--project-name orbita-api --file "$PWD/docker-compose.yml" --env-file "$PWD/.env")

# Force the env_file used by this extracted bundle, regardless of the caller's settings.
export ORBITA_PROD_ENV_FILE="$PWD/.env"

"${COMPOSE[@]}" config --quiet
