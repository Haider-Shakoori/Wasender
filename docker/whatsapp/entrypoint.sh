#!/usr/bin/env sh
set -eu

mkdir -p /var/lib/wasender/whatsapp/auth
mkdir -p /var/lib/wasender/whatsapp/callback-outbox
chown -R node:node /var/lib/wasender

exec gosu node "$@"
