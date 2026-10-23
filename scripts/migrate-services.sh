#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
mode="${1:-production}"
if [[ $# -gt 0 ]]; then shift; fi
case "$mode" in
  production) settings=.env ;;
  local) settings=.env.local ;;
  *) echo 'Usage: migrate-services.sh [local|production] [--pretend] [--force]' >&2; exit 2 ;;
esac
python3 "$root/scripts/check-migrations.py"
cd "$root/ms/orbita-db"
[[ -f vendor/autoload.php ]] || { echo 'Run composer install in ms/orbita-db first.' >&2; exit 1; }
[[ -s "$settings" ]] || { echo "Prepare ms/orbita-db/$settings from its example; production uses the local SSH tunnel." >&2; exit 1; }
if [[ "$mode" == local ]]; then
  exec php artisan --env=local orbita:service-migrate "$@"
fi
exec php artisan orbita:service-migrate "$@"
