#!/usr/bin/env bash
set -euo pipefail

ROOT="${ROOT:-$(pwd)}"
COMPOSE="${COMPOSE:-compose.production.yml}"
BACKUP_ROOT="${BACKUP_ROOT:-$ROOT/backups}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DEST="$BACKUP_ROOT/$STAMP"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

cd "$ROOT"
mkdir -p "$DEST"
chmod 700 "$BACKUP_ROOT" "$DEST"

if [[ ! -f .env || ! -f "$COMPOSE" ]]; then
  echo "ERROR: run from the Wasender production repository root."
  exit 1
fi

echo "Creating MySQL logical backup..."
docker compose -f "$COMPOSE" exec -T mysql sh -lc   'exec mysqldump --single-transaction --quick --routines --triggers --events --set-gtid-purged=OFF -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'   | gzip -9 > "$DEST/mysql.sql.gz"

echo "Creating Laravel storage backup..."
docker compose -f "$COMPOSE" exec -T app   tar -C /var/www/html -czf - storage > "$DEST/laravel-storage.tar.gz"

echo "Creating WhatsApp runtime backup..."
docker compose -f "$COMPOSE" exec -T whatsapp   tar -C /var/lib/wasender -czf - whatsapp > "$DEST/whatsapp-runtime.tar.gz"

git rev-parse HEAD > "$DEST/git-sha.txt" 2>/dev/null || printf 'unknown\n' > "$DEST/git-sha.txt"
docker compose -f "$COMPOSE" images --format json > "$DEST/images.json" 2>/dev/null || true
date -u --iso-8601=seconds > "$DEST/created-at.txt"

(
  cd "$DEST"
  sha256sum mysql.sql.gz laravel-storage.tar.gz whatsapp-runtime.tar.gz > SHA256SUMS
)

chmod 600 "$DEST"/*

echo "Verifying backup archives..."
gzip -t "$DEST/mysql.sql.gz"
tar -tzf "$DEST/laravel-storage.tar.gz" >/dev/null
tar -tzf "$DEST/whatsapp-runtime.tar.gz" >/dev/null
(cd "$DEST" && sha256sum -c SHA256SUMS)

if [[ "$RETENTION_DAYS" =~ ^[0-9]+$ ]] && (( RETENTION_DAYS > 0 )); then
  find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime "+$RETENTION_DAYS" -print -exec rm -rf {} +
fi

echo "Backup complete: $DEST"
echo "Copy this backup off-server to encrypted storage before considering it protected."
