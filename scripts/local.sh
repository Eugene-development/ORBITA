#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
exec docker compose --project-directory "$root/docker/local" --env-file "$root/docker/local/.env" -f "$root/docker/local/compose.yaml" "$@"
