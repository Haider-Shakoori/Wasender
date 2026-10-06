#!/usr/bin/env bash
set -euo pipefail

ROOT="${ROOT:-$(pwd)}"
COMPOSE="${COMPOSE:-compose.production.yml}"
TARGET_REF="${1:-}"

cd "$ROOT"

if [[ -z "$TARGET_REF" ]]; then
  echo "Usage: deploy/scripts/upgrade.sh <tested-git-ref>"
  exit 1
fi

echo "Creating pre-upgrade backup..."
BACKUP_RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}" bash deploy/scripts/backup.sh

PREVIOUS_SHA="$(git rev-parse HEAD)"
printf '%s\n' "$PREVIOUS_SHA" > .last-production-sha

echo "Fetching target revision..."
git fetch --all --prune
git checkout "$TARGET_REF"

echo "Building target images before replacing running containers..."
docker compose -f "$COMPOSE" build app web whatsapp worker-whatsapp worker-campaigns worker-background scheduler

echo "Requesting graceful queue shutdown..."
docker compose -f "$COMPOSE" exec -T app php artisan queue:restart || true

echo "Updating application tier..."
docker compose -f "$COMPOSE" up -d app web
docker compose -f "$COMPOSE" exec -T app php artisan migrate --force
docker compose -f "$COMPOSE" exec -T app php artisan optimize:clear
docker compose -f "$COMPOSE" exec -T app php artisan config:cache
docker compose -f "$COMPOSE" exec -T app php artisan route:cache
docker compose -f "$COMPOSE" exec -T app php artisan view:cache

echo "Updating connector and workers..."
docker compose -f "$COMPOSE" up -d whatsapp worker-whatsapp worker-campaigns worker-background scheduler

echo "Verifying services..."
docker compose -f "$COMPOSE" ps
docker compose -f "$COMPOSE" exec -T whatsapp node -e   "fetch('http://127.0.0.1:3100/health/ready').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"

echo "Upgrade complete."
echo "Previous revision: $PREVIOUS_SHA"
echo "Current revision:  $(git rev-parse HEAD)"
