#!/usr/bin/env bash
set -euo pipefail

RELEASE="${1:?Usage: activate-release.sh /var/www/wasender/releases/<release>}"
ROOT="/var/www/wasender"
CURRENT="$ROOT/current"

if [[ ! -d "$RELEASE" ]]; then
  echo "ERROR: release directory does not exist: $RELEASE"
  exit 1
fi

if [[ ! -L "$RELEASE/.env" && ! -f "$RELEASE/.env" ]]; then
  ln -s "$ROOT/shared/.env" "$RELEASE/.env"
fi

if [[ ! -L "$RELEASE/storage" ]]; then
  rm -rf "$RELEASE/storage"
  ln -s "$ROOT/shared/storage" "$RELEASE/storage"
fi

ln -sfn "$RELEASE" "$ROOT/current.next"
mv -Tf "$ROOT/current.next" "$CURRENT"

php "$CURRENT/artisan" storage:link --force || true
php "$CURRENT/artisan" queue:restart

systemctl restart wasender-whatsapp.service
systemctl restart wasender-queue-whatsapp.service
systemctl restart wasender-queue-campaigns.service
systemctl restart wasender-queue-background.service

curl --fail --silent --show-error http://127.0.0.1:3100/health/ready >/dev/null
php "$CURRENT/artisan" about >/dev/null

echo "Activated release: $RELEASE"
