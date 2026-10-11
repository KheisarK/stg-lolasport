#!/usr/bin/env bash
set -euo pipefail
LOLA_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ -d /workspace/lola-tools/bin ]]; then
  export PATH="/workspace/lola-tools/bin:$PATH"
  export npm_config_cache=/tmp/lola-npm-cache
fi
command -v php >/dev/null
command -v composer >/dev/null
command -v node >/dev/null
cd "$LOLA_ROOT/backend"
composer install --no-interaction --prefer-dist
if [[ ! -f .env ]]; then cp .env.example .env; fi
if ! php -r '$s=file_get_contents(".env"); exit(preg_match("/^APP_KEY=.+$/m",$s) ? 0 : 1);'; then php artisan key:generate; fi
if [[ ! -f database/database.sqlite ]]; then touch database/database.sqlite; fi
php artisan migrate --force --seed
cd "$LOLA_ROOT/frontend"
npm ci
printf 'Setup selesai. Buat akun dari backend, lalu node scripts/dev.mjs dari root.\n'
