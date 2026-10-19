#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/setup-env.py
python3 scripts/check-ports.py
docker compose config --quiet
echo 'Local configuration ready. No database changes were performed.'
echo 'Build: bash scripts/build.sh local. Start: docker compose up -d --wait.'
echo 'Apply service tables explicitly: bash scripts/migrate-services.sh local.'
