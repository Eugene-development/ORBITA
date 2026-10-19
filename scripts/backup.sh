#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
[[ "${1:-}" == production ]] || { echo 'Usage: backup.sh production [destination-directory]' >&2; exit 2; }
python3 scripts/check-production.py
[[ -s prod/orbita-api/cert/mysql-ca.pem ]] || { echo "Install the public MySQL CA first." >&2; exit 1; }
umask 077
destination="${2:-$PWD/runtime/backups}"
mkdir -p "$destination"
output="$destination/orbita-$(date -u +%Y%m%dT%H%M%SZ)-$(python3 -c 'import uuid; print(uuid.uuid4().hex[:8])').sql.gz"
partial="$output.partial"
backup_env=$(mktemp "${TMPDIR:-/tmp}/orbita-backup-env.XXXXXX")
trap 'rm -f "$partial" "$backup_env"' EXIT
python3 scripts/write-db-env.py prod/orbita-api/.env "$backup_env"
backup_network=$(python3 -c 'from pathlib import Path; from scripts.env_config import read_env; print(read_env(Path("prod/orbita-api/.env")).get("DB_NETWORK", "leget-db"))')
docker run --rm --network "$backup_network" --mount "type=bind,src=$PWD/prod/orbita-api/cert/mysql-ca.pem,dst=/run/certs/mysql-ca.pem,readonly" --env-file "$backup_env" "${MYSQL_BACKUP_IMAGE:-mysql:8.4}" sh -c 'MYSQL_PWD="$DB_PASSWORD" exec mysqldump --ssl-mode=VERIFY_IDENTITY --ssl-ca=/run/certs/mysql-ca.pem --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" --single-transaction --quick --routines --events --triggers --no-tablespaces "$DB_DATABASE"' | gzip > "$partial"
gzip -t "$partial"
mv "$partial" "$output"
echo "Backup created: $output. Verify restoration on an isolated database before cutover."
