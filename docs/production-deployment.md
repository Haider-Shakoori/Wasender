# Production deployment

This runbook defines the production deployment package for Wasender. Docker Compose is the single production process manager. Do not add PM2, Supervisor or parallel host-managed Node/PHP workers beside it.

## Architecture

```text
Internet
   |
Host Nginx + TLS
   |
127.0.0.1:8080
   |
Compose web (Nginx)
   |
Compose app (PHP-FPM / Laravel)
   |---- MySQL 8.4
   |---- Redis 7.4
   |
Compose whatsapp
   |
isolated Node session workers
   |
Chromium / WhatsApp Web
```

Only host SSH and HTTP/HTTPS are public. MySQL, Redis, PHP-FPM and the WhatsApp connector have no published ports.

## Production files

- `compose.production.yml`
- `docker/php/Dockerfile`
- `docker/nginx/Dockerfile`
- `docker/nginx/default.conf`
- `docker/whatsapp/Dockerfile`
- `deploy/nginx/wasender.conf`
- `deploy/env/laravel.env.example`
- `deploy/env/whatsapp.env.example`
- `deploy/scripts/preflight.sh`
- `deploy/logrotate/wasender`

## Server prerequisites

Use a supported Ubuntu LTS host with Docker Engine and the Docker Compose plugin, Git, Nginx, curl and TLS tooling.

The application images provide PHP 8.3, Node 22, Chromium and application dependencies. The Compose stack supplies MySQL 8.4 and Redis 7.4.

Before the first production command, confirm:

- server address and SSH access
- production domain DNS points to the VPS
- backup destination and retention policy
- SMTP settings
- Stripe or other billing credentials if enabled
- internal canary tenant and dedicated test WhatsApp number

## Environment files

Copy the repository examples:

```bash
cp deploy/env/laravel.env.example .env
cp deploy/env/whatsapp.env.example whatsapp-service/.env
chmod 640 .env whatsapp-service/.env
```

Generate strong independent secrets. The same `WHATSAPP_HMAC_SECRET` must exist in both files.

Required production values include:

- `APP_KEY`
- database credentials
- `MYSQL_ROOT_PASSWORD`
- `REDIS_PASSWORD`
- mail credentials
- `WHATSAPP_HMAC_SECRET`
- `WHATSAPP_CALLBACK_KEY`
- production URL/domain

Never commit production environment files.

Laravel uses these internal Compose hosts at runtime:

```text
DB_HOST=mysql
REDIS_HOST=redis
WHATSAPP_CONNECTOR_URL=http://whatsapp:3100
```

The connector uses the private Compose web service for callbacks:

```text
LARAVEL_CALLBACK_URL=http://web/internal/whatsapp/events
LARAVEL_INTERNAL_BASE_URL=http://web
```

The Compose file overrides these internal addresses automatically.

## Persistent data

The following named volumes are persistent and must not be deleted during normal deployment:

```text
mysql-data
redis-data
laravel-storage
whatsapp-runtime
```

`whatsapp-runtime` contains LocalAuth/session state, callback outbox data, direct-message reconciliation data and campaign transport state.

Deleting it can force WhatsApp accounts to scan QR codes again and can destroy transport reconciliation state.

## Preflight

Run:

```bash
bash deploy/scripts/preflight.sh
```

The script verifies Docker/Compose, required environment files, mandatory secrets, matching HMAC secrets and Compose syntax.

Do not continue while preflight fails.

## Build

From the repository root:

```bash
docker compose -f compose.production.yml build --pull
```

The application image:

- installs production Composer dependencies
- builds Vite assets
- enables required PHP extensions

The WhatsApp image:

- installs Chromium
- installs locked Node dependencies
- runs TypeScript validation
- runs Node tests
- builds production JavaScript
- removes development-only packages

A failed build must block deployment.

## Start infrastructure and application

For a new deployment:

```bash
docker compose -f compose.production.yml up -d mysql redis
docker compose -f compose.production.yml up -d app web
```

Before migrating an existing production database, verify a recent backup. Never run `migrate:fresh` in production.

Then:

```bash
docker compose -f compose.production.yml exec app php artisan optimize:clear
docker compose -f compose.production.yml exec app php artisan migrate --force
docker compose -f compose.production.yml exec app php artisan config:cache
docker compose -f compose.production.yml exec app php artisan route:cache
docker compose -f compose.production.yml exec app php artisan view:cache
```

Start asynchronous services:

```bash
docker compose -f compose.production.yml up -d whatsapp worker-whatsapp worker-campaigns worker-background scheduler
```

Queue workloads are separated deliberately:

- `worker-whatsapp`: sessions, direct messages, inbox, chatbot and default jobs
- `worker-campaigns`: campaign preparation, dispatch, retries and reconciliation
- `worker-background`: automations, integrations and contact imports

This prevents a campaign backlog from starving interactive WhatsApp work.

## WhatsApp runtime protection

The connector runs one isolated child process per active WhatsApp session.

Production defaults include:

```text
SESSION_WORKER_MAX_ACTIVE=50
WHATSAPP_AUTH_DISK_CRITICAL_PERCENT=95
SESSION_WORKER_MAX_RESTARTS=5
SESSION_WORKER_RESTART_WINDOW_SECONDS=600
```

These are safety ceilings, not sales capacity promises. Determine real session capacity from measured VPS CPU, RAM and disk behavior before increasing them.

The connector process listens on port 3100 inside the private Compose network only. It must never be published directly to the internet.

## Host Nginx and HTTPS

Copy the host proxy template:

```bash
sudo cp deploy/nginx/wasender.conf /etc/nginx/sites-available/wasender
sudo ln -s /etc/nginx/sites-available/wasender /etc/nginx/sites-enabled/wasender
```

Replace `wasender.example.com` with the production hostname.

The host proxy sends traffic to:

```text
127.0.0.1:8080
```

which is the only published Compose application port.

Validate Nginx before reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Issue TLS only after DNS points to the VPS. Redirect HTTP to HTTPS and verify certificate renewal.

## Firewall

A single-host baseline should permit only SSH and HTTP/HTTPS.

Do not expose:

- 3306 MySQL
- 6379 Redis
- 9000 PHP-FPM
- 3100 WhatsApp connector

## Health checks

After deployment:

```bash
curl --fail https://your-domain.example/health
docker compose -f compose.production.yml ps
docker compose -f compose.production.yml exec whatsapp node -e "fetch('http://127.0.0.1:3100/health/ready').then(r=>process.exit(r.ok?0:1)).catch(()=>process.exit(1))"
docker compose -f compose.production.yml logs --tail=100 whatsapp worker-whatsapp worker-campaigns worker-background scheduler
```

Also verify Platform → Operations and confirm:

- connector online
- no crash loops
- scheduler heartbeat current
- queue backlog normal
- callback backlog normal
- disk pressure normal
- worker capacity below critical threshold

## Graceful upgrades

Before replacing running containers:

```bash
docker compose -f compose.production.yml exec app php artisan queue:restart
docker compose -f compose.production.yml up -d --build app web
docker compose -f compose.production.yml exec app php artisan migrate --force
docker compose -f compose.production.yml up -d --build whatsapp worker-whatsapp worker-campaigns worker-background scheduler
```

The connector handles SIGTERM and attempts graceful session shutdown. Do not manually kill Chromium/Node processes unless performing controlled incident recovery.

## Backup requirements

Back up at minimum:

1. MySQL
2. `laravel-storage`
3. `whatsapp-runtime`

Keep encrypted off-server copies and test restoration.

Do not treat Redis as the source of truth for domain/message state. Database records remain authoritative.

## First production canary

Use an internal test tenant and dedicated WhatsApp number.

Validate:

1. user registration
2. trial assignment
3. email verification
4. QR creation
5. WhatsApp link
6. connector restart without new QR
7. VPS/container restart without new QR
8. direct text
9. media
10. inbound reply
11. delivered/read event
12. 5–7 second same-session pacing
13. independent concurrent dispatch from two sessions
14. campaign pause/resume
15. isolated worker crash/recovery
16. uncertain-send reconciliation without blind resend
17. callback recovery after temporary Laravel outage
18. subscription expiry safely pauses the session
19. renewal restores runtime access
20. platform incident and independent external alert

Do not use a customer's live production number for the first canary.

## Rollback

Keep the previous tested Git revision/image build available.

A code rollback should preserve all named volumes. Never run `docker compose down -v` during an application rollback.

Database rollback is not automatic. Prefer forward-compatible migrations. Restore a database backup only after reviewing the migration and confirming the restore point.

## Production readiness gate

Production is considered ready for the live WhatsApp canary only when:

```text
DOMAIN: configured
HTTPS: healthy
MYSQL: healthy
REDIS: healthy
APP: healthy
WORKERS: healthy
SCHEDULER: healthy
WHATSAPP CONNECTOR: healthy
BACKUP: verified
ALERT CHANNEL: verified
CANARY TENANT: prepared
CANARY NUMBER: prepared
```

Because the connector uses the unofficial WhatsApp Web linked-device mechanism, upstream WhatsApp changes can still require a connector/library update. The deployment architecture reduces infrastructure/process failures but cannot remove that upstream compatibility risk.
