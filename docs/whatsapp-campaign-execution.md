# WhatsApp campaign execution — Part 3

Part 3 adds Laravel-side execution runs, mutable recipient execution state, dispatch attempts, usage reservations, bounded queue claiming, tenant-ready session allocation, retry policy, pause/resume/cancel controls, scheduled launch, and reconciliation.

An execution is bound to one completed active preparation, campaign version, and payload hash. Recipient execution rows reference immutable Part 2 snapshots and are initialized in bounded inserts. Claims are ordered, row-locked, overlap-protected, and limited by tenant, campaign, and session capacity.

Immediately before dispatch preparation, the current contact is checked again for tenant ownership, active state, canonical phone, granted unexpired consent, opt-out, suppression, and block. Ineligible rows become skipped without usage consumption.

Session selection supports single, selected pool, and automatic pool. Only tenant-owned `ready` sessions with database-observed capacity qualify. Allocation is deterministic least-loaded selection; it is an operational safety control, not rotation for evasion.

Usage follows reserve → consume → release. Part 3 reserves expected capacity at launch. Consumption remains zero until Part 4 confirms transport success. Skipped, failed-before-send, and cancelled work can release unused units.

Queue topology:

- `campaign-control`: start and continue execution.
- `campaign-dispatch`: recipient eligibility, session allocation, and attempt preparation.
- `campaign-retry`: bounded due retry work.
- `campaign-reconciliation`: counter and stale-claim recovery.

The registered `PendingNodeCampaignTransport` never contacts Node. Dispatch attempts stop at `transport_pending`; recipients are never marked sent, delivered, or read. Transport request DTOs contain UUIDs, bounded content, attachment metadata/checksum, deterministic idempotency, and no session credentials or database IDs.

Pause stops new claims and reaches paused only at a safe boundary. Resume rechecks subscription, campaign bindings, preparation, and sessions. Cancellation marks unsent work cancelled, preserves snapshots/attempt history, and releases unused reservation capacity. Reconciliation restores stale claims and never treats transport-pending work as successful.

Part 4 replaces only the transport implementation and adds authoritative transport results and acknowledgements. Comprehensive concurrency and delivery testing remains deferred to Part 6.
