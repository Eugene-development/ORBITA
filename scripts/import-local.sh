#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
file="${1:?Usage: import-local.sh /absolute/path/schema.sql}"
[[ -f "$file" ]] || { echo 'Schema file missing' >&2; exit 1; }
python3 - <<'PY'
from pathlib import Path
import sys
sys.path.insert(0, 'scripts')
from env_config import read_env
env=read_env(Path('.env'))
assert env.get('APP_ENV')=='local' and env.get('DB_HOST')=='mysql' and env.get('DB_DATABASE','').endswith('_local'), 'Import is restricted to isolated local DB.'
PY
python3 scripts/check-schema-export.py "$file"
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" exec mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"' < "$file"
echo 'Schema imported into isolated local DB. Catalog writes remain disabled.'
