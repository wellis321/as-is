#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"
PORT="${1:-8890}"

echo "Starting AS-IS app at http://localhost:${PORT}"
echo "MySQL settings are read from .env"
echo ""
php -S "localhost:${PORT}" -t public
