#!/usr/bin/env bash
set -euo pipefail
LOLA_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
if [[ -d /workspace/lola-tools/bin ]]; then export PATH="/workspace/lola-tools/bin:$PATH"; fi
cd "$LOLA_ROOT/backend"
composer validate --strict
php vendor/bin/pint --test
php artisan test
cd "$LOLA_ROOT/frontend"
npm run lint
npm run typecheck
npm run build
