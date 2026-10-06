# Production deployment

This runbook prepares a controlled Linux deployment at `/var/www/wasender`. Docker Compose is the repository's canonical process manager; do not add Supervisor or PM2 beside it.

## 1. Server prerequisites

Install Docker Engine with the Compose plugin, Git, and a TLS-capable host proxy (Nginx or equivalent). Permit only SSH and HTTP/HTTPS through the firewall. Use SSH keys, keep MySQL and Redis off public interfaces, and configure host/container log rotation. The Compose stack uses PHP 8.3, Node 22, MySQL 8.4, Redis 7.4, Chromium, Laravel workers, the scheduler, and the WhatsApp connector.

Recommended host: a currently supported Ubuntu LTS release. The application constraints are PHP `^8.3` with PDO MySQL and PCNTL, Laravel 12, Node 22, MySQL 8.4, Redis 7.4, Composer 2, and npm supplied by Node 22. PHP, Composer, Node, Chromium, MySQL, and Redis are installed in the pinned project images; the host needs only the deployment tooling:

```bash
sudo apt update
sudo apt install -y ca-certificates curl git nginx certbot python3-certbot-nginx
# Install Docker Engine and the Docker Compose plugin from Docker's official Ubuntu repository.
docker --version
docker compose version
```

Do not install Supervisor or PM2: Docker Compose is the single process manager. Do not publish Compose ports for MySQL, Redis, PHP-FPM, or the Node connector. Allow the configured SSH port plus HTTP/HTTPS; bind the application web port to loopback behind host Nginx.

Before any server command, the operator must provide:

- `REQUIRED FROM OPERATOR: Server address, SSH user/port, and confirmed Ubuntu release`
- `REQUIRED FROM OPERATOR: Git repository URL or approved release-upload source`
- `REQUIRED FROM OPERATOR: Production domain already pointing to the server`
- `REQUIRED FROM OPERATOR: Production database name/user/host and secret-storage method`
- `REQUIRED FROM OPERATOR: Redis and HMAC secrets through server secret storage`
- `REQUIRED FROM OPERATOR: SMTP configuration and sender identity`
- `REQUIRED FROM OPERATOR: Backup destination/retention confirmation`
- `REQUIRED FROM OPERATOR: Internal canary tenant, dedicated test number, and authorized recipient`

## 2. Prepare the release

```bash
sudo mkdir -p /var/www/wasender
sudo chown -R deploy:www-data /var/www/wasender
cd /var/www/wasender
git clone <repository-url> .
cp laravel-app/.env.example laravel-app/.env
cp whatsapp-service/.env.example whatsapp-service/.env
```

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://example.com`, and generate `APP_KEY` with `php artisan key:generate --show`. Replace every blank password/secret with a unique server-secret value. `WHATSAPP_HMAC_SECRET` must be identical in both environment files. Keep both files mode `640`, owned by the deployment user and application group, and never commit them.

Mandatory environment groups:

- **Application:** `APP_NAME`, `APP_ENV=production`, server-generated `APP_KEY`, `APP_DEBUG=false`, `APP_URL`, locale/timezone/currency, support email.
- **Database:** `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, plus matching Compose `MYSQL_*` initialization values for a new database only.
- **Redis:** `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`; never use an empty production password.
- **Queue/session:** `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, queue retry/block settings, `SESSION_DRIVER=database`, encryption and secure-cookie settings.
- **Mail:** `MAIL_MAILER`, host, port, scheme, username/password, and verified from address/name.
- **WhatsApp connector:** internal connector URL, queue/timeouts, lease/stale/reconnect values, persistent auth root, Laravel callback/internal URLs, and connector instance ID.
- **Internal HMAC:** one generated `WHATSAPP_HMAC_SECRET` shared by Laravel and Node; optional callback key where deployed. Never print or commit either.
- **Integrations/filesystem/security:** signature tolerance, bounded rate limits, `INTEGRATIONS_ALLOW_LOCALHOST=false`, `FILESYSTEM_DISK=local`, private attachment/import disks, and HTTPS session-cookie settings.

Use `<GENERATE_SECURE_VALUE>` or `<PROVIDE_PRODUCTION_VALUE>` in operator worksheets. Generate the Laravel key on the server with `docker compose run --rm app php artisan key:generate`; do not invent it manually.

For Compose, the Laravel environment also supplies `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, and `MYSQL_ROOT_PASSWORD`. Set `DB_*` to the matching non-root application account. Set a strong `REDIS_PASSWORD` and use the same value in Laravel's Redis configuration. Configure a real SMTP transport; do not retain the log mailer. Keep `FILESYSTEM_DISK=local`, attachment disks private, `INTEGRATIONS_ALLOW_LOCALHOST=false`, and the connector URL internal (`http://whatsapp:3100`).

MySQL must use utf8mb4, a dedicated least-privilege application user, restricted network access, and verified backups. Redis must use authentication, remain on the private Compose network, and never be publicly published.

## 3. Build and start

Frontend and connector artifacts are built inside the production images with `npm ci` and `npm run build`. The equivalent non-container release commands are:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Deploy with:

```bash
docker compose build --pull
docker compose up -d mysql redis app web
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose up -d worker scheduler whatsapp
```

Before migration, confirm and record without displaying any password:

```text
DATABASE: MySQL 8.4 / utf8mb4
HOST: <CONFIRMED_PRODUCTION_DB_HOST>
DATABASE NAME: <CONFIRMED_PRODUCTION_DB_NAME>
ENVIRONMENT: production
BACKUP: <VERIFIED_BACKUP_ID> or NEW EMPTY DATABASE
```

Stop before `php artisan migrate --force` unless every field is confirmed. Never run `migrate:fresh` in production. For an existing database, require a verified backup first; for a first deployment, explicitly record that the database is new and empty. Never put database passwords directly in shell-history examples.

If building outside Docker on the current Windows workstation, use the bundled Node executable with the project-local Vite/TypeScript entrypoints because the machine-wide npm launcher is incomplete. Linux production should use the standard `npm ci` and `npm run build` commands above.

## 4. Storage and processes

The `storage/` and `bootstrap/cache/` directories must be writable by the PHP-FPM user without world-writable permissions:

```bash
sudo chown -R deploy:www-data laravel-app/storage laravel-app/bootstrap/cache
sudo find laravel-app/storage laravel-app/bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find laravel-app/storage laravel-app/bootstrap/cache -type f -exec chmod 664 {} \;
```

Do not run `chmod -R 777`. Do not run `storage:link`: WhatsApp attachments, template media, imports, and connector auth state are private and must remain outside the public web root.

The single `worker` service consumes all configured application queues with three attempts, a 120-second timeout, and a one-hour recycling window. The `scheduler` service runs Laravel's scheduler continuously, including session health, expiry, campaigns, automation, subscription, integration-webhook, and heartbeat reconciliation. Compose restarts both automatically.

Use graceful restarts:

```bash
docker compose exec app php artisan queue:restart
docker compose up -d --no-deps worker
docker compose up -d --no-deps whatsapp
```

The connector handles SIGTERM, drains HTTP work, closes sessions, and persists auth state in `whatsapp-auth`. Never kill it blindly or delete that volume during a release.

## 5. Nginx and HTTPS

`docker/nginx/default.conf` points only to Laravel's `public/` directory, disables directory listing, blocks hidden files, uses Laravel `try_files`, limits uploads to 26 MB, and forwards PHP to PHP-FPM. Replace `example.com` with the deployment hostname.

Place the `web` service behind a host TLS proxy. Issue and renew a certificate with Let's Encrypt/Certbot, redirect HTTP to HTTPS at that proxy, forward `Host`, `X-Forwarded-For`, and `X-Forwarded-Proto`, and expose only ports 80/443 publicly. The Compose port `8080` should be bound to loopback or firewall-restricted when the TLS proxy runs on the host.

Do not request a certificate until the real production domain resolves to this server. After DNS is confirmed, use `sudo certbot --nginx -d <PRODUCTION_DOMAIN>` and verify automatic renewal. The Node connector remains private on the Compose network.

## 6. Health and operational verification

```bash
curl --fail https://example.com/health
docker compose exec whatsapp node -e "fetch('http://127.0.0.1:3100/health/ready').then(r=>{if(!r.ok)process.exit(1);return r.text()}).then(console.log)"
docker compose ps
docker compose logs --tail=100 worker scheduler whatsapp
```

The public Laravel health response is intentionally bounded. Keep connector `/internal/health` private; `/health/ready` is suitable only for an internal container check. Confirm `/platform/operations`, the scheduler heartbeat, failed jobs, and queue backlog using an authorized platform account.

## 7. Backups and restore

Before every deployment, back up MySQL plus the `attachments-data` and `whatsapp-auth` volumes. `attachments-data` contains all Laravel private media; `whatsapp-auth` contains connector authentication and transport-correlation state. Encrypt backups, keep off-server copies, define retention, and test restores regularly.

Restore in this order: stop workers and connector gracefully; verify the backup; restore MySQL; restore private media; restore connector auth state; clear and rebuild Laravel caches; start application, workers, scheduler, and connector; verify health; then reconcile uncertain messages and campaigns. Never delete message attempts or session state to force recovery.

## 8. Controlled canary

Keep broader tenants disabled until all steps pass:

1. Log in with one internal tenant account and confirm the dashboard and WhatsApp session page load.
2. Connect one dedicated test WhatsApp number and confirm its QR connection.
3. Send one message to an authorized test recipient and confirm sent/delivered callbacks.
4. Send one inbound reply and confirm Shared Inbox ingestion.
5. Confirm usage increments exactly once and logs/queues show no duplicate processing.

Do not use a customer's production WhatsApp number for the first canary.

## 9. Rollback

1. Enable maintenance mode when required and gracefully stop new queue consumption and the connector.
2. Restore the previous release code/image tags.
3. Restore the database only when the migration rollback requires it, the migration is understood, and the backup is verified.
4. Preserve private media, message attempts, and WhatsApp session/auth state.
5. Run `optimize:clear`, rebuild config/routes/views, and restart the application and scheduler.
6. Restart workers with `queue:restart`, then gracefully restart the connector.
7. Reconcile uncertain messages/campaigns and verify Laravel and connector health before leaving maintenance mode.

## 10. Production checklist

- Confirm HTTPS, firewall, SSH-key access, non-public MySQL/Redis, `APP_DEBUG=false`, protected platform routes, secret-file permissions, backups, monitoring, alerts, and log rotation.
- Confirm all migrations are complete, queue/scheduler/connector services are healthy, and no sensitive values appear in logs.
- Record the release revision and backup identifiers before beginning the canary.

## 11. Stage 2 canary checkpoint

Stop before scanning a QR code. Complete this status record using safe values only:

```text
SERVER: <RUNNING|FAILED|NOT CONFIGURED>
DOMAIN: <CONFIGURED|NOT CONFIGURED>
HTTPS: <RUNNING|FAILED|NOT CONFIGURED>
DATABASE: <RUNNING|FAILED|NOT CONFIGURED>
REDIS: <RUNNING|FAILED|NOT CONFIGURED>
QUEUE: <RUNNING|FAILED|NOT CONFIGURED>
SCHEDULER: <RUNNING|FAILED|NOT CONFIGURED>
NODE: <RUNNING|FAILED|NOT CONFIGURED>
LARAVEL HEALTH: <HTTP 200|FAILED|NOT CHECKED>
NODE HEALTH: <HTTP 200|FAILED|NOT CHECKED>
BACKUP: <VERIFIED|FAILED|NOT CONFIGURED>
CANARY TENANT: <PREPARED|NOT PREPARED>
```

Output `READY FOR WHATSAPP CANARY` only when every service is running, HTTPS and backups are verified, both health checks return HTTP 200, and the internal canary tenant/test identities are prepared. Otherwise output `NOT READY FOR WHATSAPP CANARY` and list only the unmet items. Stage 3 alone may scan the QR code or send/receive a live message.
