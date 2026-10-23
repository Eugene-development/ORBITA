#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/compose.sh"
"${COMPOSE[@]}" pull
"${COMPOSE[@]}" up -d
"${COMPOSE[@]}" ps
