# Operations

## Queues and scheduler

Production uses Redis queues with database failed-job retention. The Compose worker consumes the message, session, campaign, automation, import, and default queues with three attempts, a 120-second timeout, and hourly worker recycling. Scale the existing worker service when backlog requires it; do not run a second queue architecture.

The scheduler runs the existing bounded subscription, session, message, campaign, automation, invitation, and heartbeat commands. High-risk work uses overlap locks and a single-server lock. Outside Compose, run `php artisan schedule:run` once per minute from cron.

## Health and reconciliation

`/health` is a public, shallow database/cache/queue readiness response. `/platform/operations` and existing queue, health, campaign, message, and automation screens require platform authorization. The connector exposes safe liveness/readiness endpoints and an authenticated internal health response.

Session auth data is persisted under the connector auth volume. Each session has an atomic lease in that volume, renewed with heartbeats and released during clean shutdown. An expired lease permits takeover. Existing Laravel session monitoring initiates bounded reconnects.

Unknown transport results are never blindly resent. Use the existing message, campaign transport, campaign execution, and automation reconciliation commands/screens. Failed jobs remain in Laravel's failed-job table and may be safely reviewed before retry.

## Backups and incidents

Back up MySQL and the persistent private WhatsApp media and connector-auth volumes. Keep secrets outside the repository. Recommended retention is 7 daily, 4 weekly, and 3 monthly copies, encrypted and stored away from the application host.

For an incident, preserve message attempts and unknown states, stop new dispatch, check `/platform/operations`, restore dependencies, restart with `php artisan queue:restart` and the Compose connector service, then reconcile. Never mark uncertain sends failed or resend them with new idempotency keys without investigation.
