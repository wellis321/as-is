#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"
PORT="${1:-8890}"

echo "Starting AS-IS app at http://localhost:${PORT}"
echo "MySQL settings are read from .env"
echo ""
# PHP_CLI_SERVER_WORKERS allows concurrent requests so AJAX calls
# don't get a 503 while the main page is still loading.
PHP_CLI_SERVER_WORKERS=4 php -S "localhost:${PORT}" -t public
