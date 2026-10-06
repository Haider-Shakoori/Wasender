#!/usr/bin/env bash
set -euo pipefail

ROOT="${ROOT:-$(pwd)}"
COMPOSE="${COMPOSE:-compose.production.yml}"
TARGET_REF="${1:-}"

cd "$ROOT"

if [[ -z "$TARGET_REF" && -f .last-production-sha ]]; then
  TARGET_REF="$(cat .last-production-sha)"
fi

if [[ -z "$TARGET_REF" ]]; then
  echo "Usage: deploy/scripts/rollback-code.sh <previous-tested-ref>"
  exit 1
fi

echo "WARNING: this rolls back application code/images only. It does not reverse database migrations."
git fetch --all --prune
git checkout "$TARGET_REF"

docker compose -f "$COMPOSE" build app web whatsapp worker-whatsapp worker-campaigns worker-background scheduler
docker compose -f "$COMPOSE" exec -T app php artisan queue:restart || true
docker compose -f "$COMPOSE" up -d app web whatsapp worker-whatsapp worker-campaigns worker-background scheduler

docker compose -f "$COMPOSE" exec -T app php artisan optimize:clear
docker compose -f "$COMPOSE" exec -T app php artisan config:cache
docker compose -f "$COMPOSE" exec -T app php artisan route:cache
docker compose -f "$COMPOSE" exec -T app php artisan view:cache

echo "Code rollback complete at $(git rev-parse HEAD). Review database compatibility before declaring recovery complete."
