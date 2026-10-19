#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 scripts/check-migrations.py
for service in front admin; do
 npm --prefix "ms/orbita-$service" run check
 npm --prefix "ms/orbita-$service" run build
done
npm --prefix scripts test
composer --working-dir=ms/orbita-api test
python3 scripts/test-infra.py
while IFS= read -r file; do bash -n "$file"; done < <(find scripts deploy prod -type f -name '*.sh')
echo 'Source checks passed. Docker/MySQL and browser checks run separately.'
