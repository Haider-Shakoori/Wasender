# Shared inbox foundation

Conversations are tenant-scoped and uniquely identified by tenant, WhatsApp session, and direct `@c.us` address. The signed Node connector emits stable, idempotent inbound events; Laravel validates session ownership, resolves the normalized tenant contact without granting consent, stores the inbox message, and atomically updates unread and last-message fields.

Existing transactional outbound messages remain authoritative and receive one inbox projection linked to the matching conversation. Inbound media is represented by bounded MIME, size, checksum, and opaque private retrieval metadata with a pending state; no public media URL is created.

## Agent operations

Part 2 adds active-member assignment, open/pending/closed/archived transitions, low-to-urgent priority, and shared read/unread state. New inbound messages reopen closed conversations but preserve explicit archives, assignment, priority, labels, and notes.

Internal plain-text notes support explicit active tenant-member mentions through database notifications. Tenant saved replies remain plain-text helpers and never send automatically. Lightweight conversation labels and an append-only, body-free activity history support the Part 3 interface.

## Complete inbox interface

The tenant interface provides overview queues, filtered conversation lists, bounded message timelines, responsive agent controls, private text/media replies through canonical transactional messaging, saved-reply and published-template insertion, notes, mentions, labels, and activity. Platform operators receive read-only, masked conversation, message, and failure views.

Polling remains intentionally lightweight and WebSockets, presence, typing, automatic routing, AI, group chats, and advanced analytics are deferred. Automated testing remains deferred until the full project release gate.
