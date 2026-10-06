#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-$(pwd)}"

cd "$APP_ROOT"

if [[ ! -f composer.json || ! -f artisan ]]; then
  echo "ERROR: $APP_ROOT is not a Wasender Laravel release."
  exit 1
fi

composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader

npm ci
npm run build

cd "$APP_ROOT/whatsapp-service"
npm ci
npm run typecheck
npm test
npm run build

cd "$APP_ROOT"

php artisan optimize:clear
php artisan migrate --force
php artisan optimize

echo "Release build complete: $APP_ROOT"
