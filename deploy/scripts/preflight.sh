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

need php
need composer
need node
need npm
need nginx
need systemctl
need redis-cli
need mysql
need chromium

php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' || {
  echo "ERROR: PHP 8.3+ is required."
  fail=1
}

node -e 'const m=Number(process.versions.node.split(".")[0]); process.exit(m >= 22 ? 0 : 1)' || {
  echo "ERROR: Node.js 22+ is required."
  fail=1
}

if [[ ! -d /var/lib/wasender/whatsapp ]]; then
  echo "MISSING: /var/lib/wasender/whatsapp"
  fail=1
fi

if [[ ! -f /etc/wasender/laravel.env ]]; then
  echo "MISSING: /etc/wasender/laravel.env"
  fail=1
fi

if [[ ! -f /etc/wasender/whatsapp.env ]]; then
  echo "MISSING: /etc/wasender/whatsapp.env"
  fail=1
fi

if [[ "$fail" -ne 0 ]]; then
  echo "Preflight failed."
  exit 1
fi

echo "Preflight passed."
