#!/usr/bin/env bash
set -euo pipefail
echo 'orbita-db is a local Laravel service; it has no Docker build or push.' >&2
echo 'See ms/orbita-db/README.md and run migrations locally through the SSH tunnel.' >&2
exit 2
