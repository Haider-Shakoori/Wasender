# Batch 01 — Architecture & Wasender Workflow Audit

Date: 2026-10-06
Branch: `batch-01-architecture-audit`

## Goal

Audit the current Laravel + Node WhatsApp service against the target self-hosted Wasender-style product before implementation begins.

The target product is a multi-tenant SaaS where users connect normal WhatsApp accounts through Linked Devices / QR, keep sessions on the VPS, send through a durable queue with per-session pacing, receive inbound messages/webhooks, and subscribe through configurable plans/payment gateways.

## External product behavior reviewed

Current Wasender-style products commonly use these patterns:

- A WhatsApp session/channel represents one connected WhatsApp number.
- A new session begins pending and is linked by QR.
- QR codes rotate and the UI must refresh them.
- Credentials are persisted after the first successful scan so ordinary reconnects do not require a new QR.
- Restart/reconnect is different from logout/unlink. Restart should preserve credentials; logout/unlink intentionally discards them.
- Health/status endpoints expose whether a number is connected/ready.
- Session status/disconnect events are delivered to the application.
- Messaging supports text and common media types through HTTP APIs.
- Plans commonly limit connected WhatsApp sessions/numbers while allowing API/webhook access.
- Billing must be independent from WhatsApp session credentials: an expired/past-due plan should disable sending without destroying the stored WhatsApp identity.

## Existing Laravel architecture

The current repository already contains substantial SaaS infrastructure:

- Laravel 12 / PHP 8.3.
- Tenant workspace and platform-admin separation.
- Subscription plan, tenant subscription, entitlement and feature-capacity concepts.
- WhatsApp session model with persistent UUID, opaque storage key, status lifecycle, QR metadata, reconnect tracking and health timestamps.
- Tenant-safe session queries.
- Session lifecycle state machine with audited transitions.
- QR payloads are stored temporarily in cache and encrypted at rest.
- Session create/reconnect/disconnect/delete actions are queued.
- HMAC-authenticated internal callback endpoints.
- Campaigns, templates, inbox, automations, chatbots and analytics are already represented in the application.
- Platform health/queue/WhatsApp operational pages already exist and can be extended rather than replaced.

## Existing Node connector architecture

The newly added `whatsapp-service` already provides a useful foundation:

- TypeScript + Express.
- `whatsapp-web.js` with `LocalAuth`.
- Headless Chromium.
- Persistent auth root configurable through `WHATSAPP_AUTH_ROOT`.
- Per-session opaque storage keys.
- QR callback to Laravel.
- Authenticated, ready, auth-failure and disconnected events.
- Inbound message forwarding.
- Delivery/read acknowledgement mapping.
- Text/image/document/audio/video sending.
- HMAC verification for internal requests.
- Nonce protection.
- Session leases to reduce duplicate ownership.
- Connector heartbeat.
- Graceful SIGTERM/SIGINT shutdown.
- Campaign idempotency/reconciliation support.
- Campaign concurrency can already be limited per session.

## What is already aligned with the target

The current code already matches several important decisions:

1. Laravel is the control plane and Node is the WhatsApp transport plane.
2. WhatsApp auth/session data is not part of Git.
3. The Laravel session record stores an opaque storage key rather than a browser profile path.
4. QR is retrieved through the SaaS UI rather than exposing the connector directly.
5. Campaign transport already models unknown/reconciliation behavior instead of blindly resending.
6. Session capacity is enforced through tenant entitlements.
7. Internal Laravel ↔ Node requests use HMAC and nonce protection.
8. Session restart and logout are distinct concepts.
9. The connector supports graceful draining.
10. One-at-a-time campaign send capacity is already configurable.

## Critical gaps before production

### 1. Connector process isolation

The current `SessionManager` keeps all `whatsapp-web.js` clients in one Node process. A severe process-level crash, unhandled runtime fault, Chromium problem, memory leak or Node OOM can affect every connected account.

Required change:
- Introduce a connector-manager / session-worker architecture.
- Session workers must be independently restartable.
- A single broken session must not take every tenant offline.

### 2. Durable connector restoration

The Node process only knows sessions currently loaded into its in-memory map. After a process/VPS restart, Laravel needs a deterministic recovery/reconciliation flow that restores eligible sessions in controlled batches.

Required change:
- Laravel remains authoritative for which sessions should be restored.
- Node restores only requested/owned sessions.
- Startup recovery must be staggered to avoid launching many Chromium instances simultaneously.
- Readiness must be confirmed before dispatch resumes.

### 3. Connector supervision

The application code has graceful shutdown but repository deployment does not yet define production supervision.

Required change:
- systemd is preferred for the VPS service.
- Automatic restart with exponential/backoff protection.
- Start on boot.
- Memory/CPU limits and restart policy.
- Health-check integration.

### 4. Session profile growth

`LocalAuth` stores a Chromium profile and browser caches can become very large.

Required change:
- Preserve authentication state.
- Identify and clean only disposable cache directories.
- Add disk thresholds.
- Prevent cache cleanup from destroying valid linked-device credentials.
- Session data must live outside deploy releases, e.g. `/var/lib/wasender/whatsapp/`.

### 5. Queue pacing

The required default 5–7 second spacing is not yet a canonical platform-wide rule.

Required change:
- Persist a per-session next-send eligibility timestamp.
- Randomize each interval between 5 and 7 seconds by default.
- Never use a long blocking `sleep()` in Laravel workers.
- Different WhatsApp sessions can dispatch concurrently.
- Direct, campaign, automation and chatbot sends must all enter the same pacing mechanism.

### 6. Durable direct-message idempotency

Campaign transport has stronger persistence/reconciliation semantics than the ordinary direct-message request registry, which is currently in-memory.

Required change:
- Make direct-message attempts durable.
- Preserve idempotency across Node restarts.
- Persist or reconcile uncertain sends before retrying.
- Avoid duplicate messages after connector crashes.

### 7. Connector callback durability

Some session callbacks log and discard failures after a failed Laravel callback.

Required change:
- Durable callback outbox for operational/session events.
- Bounded retry/backoff.
- Idempotent Laravel ingestion.
- Preserve session/disconnect/health events during brief Laravel/network outages.

### 8. Health model

The current Node health response is intentionally small.

Required change:
Expose safe operational metrics such as:
- connector instance/version
- uptime
- active/ready/reconnecting sessions
- worker state
- Chromium process health
- restart count
- memory
- callback backlog
- dispatch backlog/capacity
- last heartbeat

No secrets, raw profile paths or message content should be exposed.

### 9. Alerting

The platform has health/operations foundations but needs actionable incidents.

Required change:
- INFO/WARNING/CRITICAL alerts.
- Connector offline.
- Session repeated crash.
- QR/auth required.
- Memory/disk thresholds.
- Queue backlog.
- Unknown-send buildup.
- Callback backlog.
- Connector/Laravel version incompatibility.
- Deduplicate repeated alerts.
- External notification transport independent from the affected WhatsApp connector.

### 10. Redis production baseline

Laravel currently defaults queues to the database driver.

Required change:
- Redis should be the production queue/cache/locking baseline.
- Database remains authoritative for durable domain/message state.
- Queue workers should be separated by workload where it materially improves isolation.

### 11. CI is missing

No active GitHub Actions workflow was found at the expected workflow paths.

Required change in Batch 02:
- Composer install/validate/audit.
- PHP tests.
- Pint.
- Fresh migration validation.
- Node install/typecheck/tests/build.
- Vite production build.
- Branch/PR quality gate.

## Subscription and payment findings

The existing code already has:
- `SubscriptionPlan`
- plan pricing/currency
- trial days
- grace days
- `TenantSubscription`
- provider/provider subscription identifiers
- payment/history relationships

This is a strong base for configurable billing.

Required target:

- Do not couple subscription logic directly to Stripe.
- Define a payment gateway contract.
- Platform settings determine enabled gateways.
- Initial adapters: Stripe + Manual Payment.
- Future providers can be added without rewriting subscription domain logic.
- Encrypted provider credentials.
- Test/live configuration.
- Signed/idempotent webhook ingestion.
- Configurable monthly/yearly/trial/grace behavior.
- A failed payment pauses entitlement/sending according to policy but does not delete WhatsApp auth data.
- Plans can limit sessions, team members, contacts, automations, campaigns, templates, storage and optional send quotas.
- Payment gateway health/status appears in platform operations.

## Recommended production boundaries

```text
Browser / API clients
        |
      Nginx
        |
      Laravel
        |
   Redis queues/locks
        |
 Connector Manager
    |    |    |
 Worker Worker Worker
    |    |    |
 Chromium per active session
        |
   WhatsApp Web
```

Persistent state:

```text
Database:
  tenants, plans, subscriptions, sessions, messages,
  attempts, campaigns, inbox, alerts, audit

Redis:
  queues, locks, short-lived cache, QR state

Filesystem outside releases:
  /var/lib/wasender/whatsapp/auth
  /var/lib/wasender/whatsapp/runtime
```

## Locked product decisions

- No Meta Cloud API for the primary transport.
- WhatsApp linked-device QR flow.
- Persistent VPS sessions.
- Default per-session send spacing: randomized 5–7 seconds.
- Different sessions may send concurrently.
- All send sources use one canonical dispatch/pacing layer.
- GitHub is the source of truth for development/testing.
- VPS/remote commander is reserved for operations that genuinely require the server.
- Node failures must be automatically detected and recovered where safe.
- Platform admin must be informed of significant failures.
- Subscription/payment gateways must be configurable in Platform settings.
- Stripe will be supported but billing architecture must be provider-agnostic.

## Batch 01 conclusion

The project does not need to be rebuilt. Most Laravel SaaS/domain work is already present and the Node service is a credible foundation.

The highest-risk production gap is reliability of the long-running WhatsApp runtime, specifically process isolation, restart restoration, durable direct-message idempotency/callbacks, resource control and alerting.

Implementation should therefore continue in this order:

1. Batch 02 — GitHub CI quality gate.
2. Batch 03 — persistent production session runtime.
3. Batch 04 — connector manager and crash isolation.
4. Batch 05 — heartbeats, health and automatic recovery.
5. Batch 06 — operations center and alerting.
6. Batch 07 — canonical 5–7 second per-session queue scheduler.
7. Batch 08 — durable idempotency/retry/reconciliation.
8. Continue remaining approved batches.

No production/VPS change is required for Batch 01.
