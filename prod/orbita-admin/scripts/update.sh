#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/compose.sh"
"${COMPOSE[@]}" pull
"${COMPOSE[@]}" up -d --force-recreate
"${COMPOSE[@]}" ps
