#!/usr/bin/env bash
set -euo pipefail

ROOT="${ROOT:-$(pwd)}"
COMPOSE="${COMPOSE:-compose.production.yml}"
BACKUP="${1:-}"

if [[ "${CONFIRM_RESTORE:-}" != "YES" ]]; then
  echo "ERROR: restore is destructive. Re-run with CONFIRM_RESTORE=YES."
  exit 1
fi

if [[ -z "$BACKUP" || ! -d "$BACKUP" ]]; then
  echo "Usage: CONFIRM_RESTORE=YES deploy/scripts/restore.sh /path/to/backup"
  exit 1
fi

for file in mysql.sql.gz laravel-storage.tar.gz whatsapp-runtime.tar.gz SHA256SUMS; do
  [[ -f "$BACKUP/$file" ]] || { echo "ERROR: missing $file"; exit 1; }
done

cd "$ROOT"
(cd "$BACKUP" && sha256sum -c SHA256SUMS)

echo "Stopping application workers and connector..."
docker compose -f "$COMPOSE" stop worker-whatsapp worker-campaigns worker-background scheduler whatsapp web app

echo "Starting database only..."
docker compose -f "$COMPOSE" up -d mysql
until docker compose -f "$COMPOSE" exec -T mysql sh -lc 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent'; do
  sleep 2
done

echo "Restoring MySQL..."
docker compose -f "$COMPOSE" exec -T mysql sh -lc   'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS \`$MYSQL_DATABASE\`; CREATE DATABASE \`$MYSQL_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"'
gunzip -c "$BACKUP/mysql.sql.gz" | docker compose -f "$COMPOSE" exec -T mysql sh -lc   'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'

echo "Starting app and restoring Laravel storage..."
docker compose -f "$COMPOSE" up -d app
docker compose -f "$COMPOSE" exec -T app sh -lc 'find storage -mindepth 1 -maxdepth 1 -exec rm -rf {} +'
cat "$BACKUP/laravel-storage.tar.gz" | docker compose -f "$COMPOSE" exec -T app tar -C /var/www/html -xzf -

echo "Restoring WhatsApp runtime..."
docker compose -f "$COMPOSE" up -d whatsapp
docker compose -f "$COMPOSE" exec -T whatsapp sh -lc 'find /var/lib/wasender/whatsapp -mindepth 1 -maxdepth 1 -exec rm -rf {} +'
cat "$BACKUP/whatsapp-runtime.tar.gz" | docker compose -f "$COMPOSE" exec -T whatsapp tar -C /var/lib/wasender -xzf -

echo "Restarting full stack..."
docker compose -f "$COMPOSE" up -d app web whatsapp worker-whatsapp worker-campaigns worker-background scheduler
docker compose -f "$COMPOSE" exec -T app php artisan optimize:clear
docker compose -f "$COMPOSE" exec -T app php artisan config:cache
docker compose -f "$COMPOSE" exec -T app php artisan route:cache
docker compose -f "$COMPOSE" exec -T app php artisan view:cache

echo "Restore complete. Run the production health and WhatsApp session-restoration checks now."
