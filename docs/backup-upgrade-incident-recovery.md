# Backup, upgrade and incident recovery

Batch 18 defines the recovery procedures for the hosted Wasender SaaS.

## Recovery objectives

Protect the three durable sources required to recover service:

1. MySQL domain data
2. Laravel storage
3. WhatsApp runtime state

The WhatsApp runtime backup is especially important because it contains linked-device LocalAuth data, callback outbox state and message/campaign reconciliation state.

Redis is operational state, not the authoritative source of domain data, so it is not part of the minimum restore set.

## Backup command

Run from the production repository root:

```bash
bash deploy/scripts/backup.sh
```

Backups are written under:

```text
backups/YYYYMMDDTHHMMSSZ/
  mysql.sql.gz
  laravel-storage.tar.gz
  whatsapp-runtime.tar.gz
  git-sha.txt
  images.json
  created-at.txt
  SHA256SUMS
```

The script verifies all archives and checksums before reporting success.

Local backups are not enough. Copy every successful backup to encrypted off-server storage.

Recommended policy:

- daily backup
- 14 daily copies
- 8 weekly copies
- 12 monthly copies
- periodic restore drill

The local helper prunes local timestamped directories older than `BACKUP_RETENTION_DAYS`, default 14 days.

## Restore drill

Never make the first restore attempt during a real outage.

Use a disposable recovery host or isolated Compose project and restore the latest backup there. Validate:

- database opens successfully
- users/tenants/subscriptions exist
- Laravel storage is readable
- WhatsApp runtime archive contains expected auth directories
- app boots
- connector boots
- sessions restore without a new QR where WhatsApp still considers the linked device valid

The production restore helper is destructive and requires an explicit guard:

```bash
CONFIRM_RESTORE=YES bash deploy/scripts/restore.sh /path/to/backup
```

Do not run it against production until the selected backup timestamp and checksum have been confirmed.

## Upgrade procedure

Upgrade only to a tested Git revision:

```bash
bash deploy/scripts/upgrade.sh <tested-git-ref>
```

The script:

1. creates a pre-upgrade backup
2. records the previous Git SHA
3. fetches and checks out the requested revision
4. builds all application/connector images before replacement
5. asks Laravel workers to exit gracefully
6. updates app/web
7. runs forward migrations
8. refreshes Laravel caches
9. updates connector/workers/scheduler
10. checks Compose state and connector readiness

WhatsApp runtime volumes are preserved throughout.

## Code rollback

If a release fails but the database remains forward-compatible:

```bash
bash deploy/scripts/rollback-code.sh
```

or specify a revision:

```bash
bash deploy/scripts/rollback-code.sh <previous-tested-ref>
```

This is a code/image rollback only.

Do not automatically run `migrate:rollback`. Migrations may have transformed or deleted data and require review.

If a database restore is required, use a verified pre-upgrade backup instead.

## Incident classes

### Connector offline

Symptoms:

- connector health fails
- all sessions appear unavailable
- worker jobs retry

Actions:

1. check `docker compose ps`
2. inspect connector logs
3. check disk and memory pressure
4. restart only the connector if necessary
5. confirm session restoration
6. do not delete WhatsApp runtime storage

### Single session crash loop

Symptoms:

- one session repeatedly reconnects/crashes
- other sessions remain healthy

Actions:

1. leave other session workers untouched
2. inspect that session's lifecycle/failure data
3. check Chromium/runtime logs
4. allow bounded supervisor restarts
5. if authentication is invalid, request QR for that one session only

### Widespread WhatsApp compatibility failure

Symptoms:

- many unrelated sessions fail at roughly the same time
- QR/auth behavior changes
- connector/Chromium errors spike without VPS resource pressure

Treat this as a possible upstream WhatsApp Web compatibility incident.

Actions:

1. stop aggressive reconnect/retry loops
2. preserve queued outbound messages
3. preserve auth/runtime storage
4. alert platform operations
5. test connector/library upgrade with an internal canary account first
6. deploy the connector update independently
7. do not mass-delete LocalAuth profiles

### Unknown send state

If the connector dies after WhatsApp may have accepted a message:

1. leave the message in unknown/ambiguous state
2. use the reconciliation path
3. never blindly enqueue a duplicate send
4. escalate unresolved cases after the configured reconciliation window

### Database outage

1. keep connector/runtime files intact
2. stop write-heavy workers if the outage is extended
3. recover MySQL
4. verify migrations/table integrity
5. resume app/workers
6. reconcile callback/message backlogs

### Redis outage

1. restore Redis service
2. do not assume queued transient data is durable domain state
3. inspect failed/pending jobs
4. rely on database status/reconciliation commands to recover work safely

### Disk pressure

At warning/critical thresholds:

1. stop nonessential builds
2. inspect Docker image/build cache
3. rotate/prune logs
4. move verified backups off-server
5. never delete `whatsapp-runtime` to free emergency space
6. never run `docker compose down -v`

### VPS loss

On a replacement host:

1. prepare Docker/Nginx/DNS
2. restore repository at the recorded Git revision
3. restore production secrets securely
4. restore MySQL
5. restore Laravel storage
6. restore WhatsApp runtime
7. start app/web
8. start connector/workers
9. validate session restoration
10. switch DNS only after health checks pass

## Connector upgrade policy

Because WhatsApp Web is unofficial and can change without notice:

- pin connector dependencies through lockfiles
- never auto-upgrade the connector in production
- validate updates with an internal WhatsApp account
- keep the previous tested image/revision
- preserve LocalAuth data across connector versions
- document any migration required by a connector library update

## Recovery rules that must never be violated

Do not:

- run `docker compose down -v` in production
- delete the WhatsApp runtime volume during ordinary troubleshooting
- blindly resend an ambiguous message
- expose MySQL, Redis or connector port 3100 publicly
- restore a backup without checksum verification
- restore over production without explicit confirmation
- automatically roll database migrations backward
