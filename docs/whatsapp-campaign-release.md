# WhatsApp campaign controlled-release runbook

## Release status

Phase 1, Batch 13 Parts 1–6 are ready for a controlled release. The campaign subsystem has a tenant-scoped Laravel control plane, immutable recipient preparation, queued execution, a signed Node transport, idempotent callbacks, usage reservation/consumption, cancellation safety, and redacted tenant/platform operations.

This verdict does not certify Meta Cloud API compatibility or guarantee continued compatibility with unofficial WhatsApp Web behavior. No real WhatsApp message was sent during release validation.

## Safety invariants

- Every campaign, preparation, recipient snapshot, execution, attempt, callback, reservation, route, and operational lookup remains tenant-scoped.
- Consent, suppression, block, validity, and deduplication checks are canonical and cannot be bypassed by UI input.
- Launch uses the prepared campaign version and payload hash; stale preparations are rejected.
- Recipient dispatch and Node transport are idempotent. Duplicate or out-of-order acknowledgements cannot double-consume usage or downgrade delivery state.
- A transport timeout is `unknown`, never proof of failure and never permission to resend blindly.
- Cancellation preserves confirmed sends and does not release capacity while processing, transport-pending, or unknown work remains.
- Internal transport requests use timestamped HMAC authentication, replay protection, bounded bodies/timeouts, strict direct-chat addressing, private expiring media URLs, and checksum verification.
- Tenant and platform operational pages redact message bodies, phone numbers, transport payloads, secrets, and auth material.

## Required processes

Run the Laravel scheduler every minute and keep bounded, supervised workers on:

```text
campaign-preparation
campaign-control
campaign-dispatch
campaign-retry
campaign-reconciliation
```

The registered scheduler commands prepare and launch due campaigns, dispatch due retries, reconcile preparations and executions, and resolve uncertain transport. Node persists dispatch/callback correlation and retries its callback outbox; keep exactly one durable data directory per Node instance and do not place it on ephemeral storage.

## Required environment

Configure Laravel database, Redis cache/queue, application URL/key, the shared internal HMAC secret, Node base URL, campaign execution and transport feature flags, timeouts, concurrency limits, attachment disk, and retention values from `laravel-app/.env.example`.

Configure Node port, Laravel callback URL, the same HMAC secret, durable WhatsApp auth root, Chromium executable, campaign limits/timeouts, callback retry policy, correlation retention, and temporary-media retention from `whatsapp-service/.env.example`. Secrets must be at least 32 random characters and must not be committed.

## Deployment checklist

1. Back up the database and durable Node auth/correlation storage.
2. Deploy Laravel and Node artifacts from the same reviewed release.
3. Install PHP dependencies from `composer.lock` and Node dependencies with `npm ci`.
4. Build Laravel assets and Node TypeScript.
5. Put Laravel into maintenance mode if the deployment topology requires it.
6. Run `php artisan migrate --force`.
7. Clear and rebuild Laravel configuration/route/view caches.
8. Restart the Node connector with its existing durable storage mounted.
9. Restart scheduler and queue supervisors with all five campaign queues.
10. Confirm Laravel-to-Node and Node-to-Laravel URLs are private/restricted.
11. Confirm both services use the same HMAC secret and synchronized clocks.
12. Confirm Redis/database connectivity and queue retry/timeout settings.
13. Confirm the private attachment disk is not publicly browsable.
14. Confirm feature flags, concurrency, and rate limits are conservative.
15. Run health checks and inspect failed jobs and Node callback outbox.
16. Create a tenant draft and validate/prepare it without launching.
17. Use an approved test account for the first controlled live send; this was not performed in automated validation.
18. Verify sent/delivered/read callbacks, usage, and redacted operational views.
19. Increase concurrency only after queue, callback, and unknown rates remain healthy.
20. Record the release time, schema version, artifact versions, and operator.

## Monitoring and rollback

Alert on failed jobs, growing campaign queues, stale preparations/executions, transport-pending or unknown attempts, callback retry exhaustion, usage reconciliation differences, session disconnects, and sustained error/rate-limit responses. Track throughput and latency by queue and tenant without logging recipient content or credentials.

Application rollback is safe only while retaining the migrated schema. Database rollback for Batch 13 is destructive to campaign records and must be done only after campaign workers, scheduler commands, and Node dispatch are stopped and a verified backup exists. Roll back migrations in order `000290`, `000280`, `000270`, `000260`; restore the matching application/Node artifacts and storage snapshot. Never resend an `unknown` attempt during rollback.

## Known limitations

- The connector uses unofficial WhatsApp Web automation, not the Meta Cloud API.
- Release validation used mocks and local transport; a real WhatsApp send requires a separately approved controlled smoke test.
- Node correlation/outbox persistence is local-instance storage, so horizontal Node scaling requires an external shared persistence design.
- Billing-provider integration, public campaign APIs, inbound inbox workflows, advanced analytics, and Phase 2 capabilities remain deferred.
