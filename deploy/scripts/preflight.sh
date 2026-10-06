#!/usr/bin/env bash
set -euo pipefail

fail=0

need() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "MISSING: $1"
    fail=1
  else
    echo "OK: $1 -> $(command -v "$1")"
  fi
}

need docker
need git
need nginx
need curl

if ! docker compose version >/dev/null 2>&1; then
  echo "MISSING: Docker Compose plugin"
  fail=1
else
  echo "OK: Docker Compose plugin"
fi

if [[ ! -f .env ]]; then
  echo "MISSING: .env"
  fail=1
fi

if [[ ! -f whatsapp-service/.env ]]; then
  echo "MISSING: whatsapp-service/.env"
  fail=1
fi

for key in APP_KEY DB_PASSWORD MYSQL_ROOT_PASSWORD REDIS_PASSWORD WHATSAPP_HMAC_SECRET; do
  if ! grep -Eq "^${key}=.+" .env; then
    echo "MISSING OR EMPTY: ${key} in .env"
    fail=1
  fi
done

if ! grep -Eq '^WHATSAPP_HMAC_SECRET=.+' whatsapp-service/.env; then
  echo "MISSING OR EMPTY: WHATSAPP_HMAC_SECRET in whatsapp-service/.env"
  fail=1
fi

laravel_hmac="$(grep '^WHATSAPP_HMAC_SECRET=' .env | head -n1 | cut -d= -f2-)"
node_hmac="$(grep '^WHATSAPP_HMAC_SECRET=' whatsapp-service/.env | head -n1 | cut -d= -f2-)"

if [[ -n "$laravel_hmac" && "$laravel_hmac" != "$node_hmac" ]]; then
  echo "ERROR: Laravel and connector WHATSAPP_HMAC_SECRET values do not match."
  fail=1
fi

if ! docker compose -f compose.production.yml config --quiet; then
  echo "ERROR: production Compose configuration is invalid."
  fail=1
else
  echo "OK: production Compose configuration"
fi

if [[ "$fail" -ne 0 ]]; then
  echo "Preflight failed."
  exit 1
fi

echo "Preflight passed."
