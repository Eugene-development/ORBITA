#!/usr/bin/env bash
set -euo pipefail
echo 'Migrations run on your local machine, not on VPS or in Docker.' >&2
echo 'Open the SSH tunnel from VPS/18, then run php artisan migrate from ms/orbita-db.' >&2
exit 2
