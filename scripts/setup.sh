#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/setup-env.py
python3 scripts/check-ports.py
bash scripts/local.sh config --quiet
echo 'Local configuration ready. No database changes were performed.'
echo 'Build: bash scripts/build.sh local. Start: bash scripts/local.sh up -d --wait.'
echo 'Prepare ms/orbita-db/.env.local and composer install in ms/orbita-db, then: bash scripts/migrate-services.sh local (local PHP; no migration container).'
