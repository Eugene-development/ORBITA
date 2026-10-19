#!/usr/bin/env bash
set -euo pipefail
case "${1:-}" in
 api|front|admin) service="$1" ;;
 *) echo "Usage: $0 <api|front|admin>" >&2; exit 1 ;;
esac
exec "$(dirname "$0")/../prod/orbita-$service/scripts/update.sh"
