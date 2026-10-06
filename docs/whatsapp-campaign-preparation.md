# WhatsApp campaign recipient preparation

Part 2 resolves tenant-owned contacts into immutable, campaign-owned recipient snapshots. It never creates outbound message rows, calls Node, or sends messages.

Audience resolvers cover all contacts, saved segments compiled through the Batch 12 allowlist, groups, labels, and manual contact UUIDs. Every resolver preserves tenant scope and contact-ID ordering. Estimates are cached briefly and are explicitly non-authoritative.

Preparation runs bind the campaign version, payload hash, and canonical audience-definition hash. A database lock permits one active run per campaign. Queue chunks claim a persisted contact-ID cursor, evaluate bounded candidates, insert snapshots/exclusions, atomically advance counters, and dispatch only the next preparation chunk.

Eligibility requires an active, non-deleted contact, canonical phone, granted unexpired consent, and no opt-out, suppression, or block. There is no bypass option. Duplicate numbers are keyed by SHA-256 of tenant ID and normalized phone and protected by a campaign-scoped unique constraint.

Eligible snapshots retain only contact UUID/link, canonical WhatsApp address, display and locale fields, source, consent state, and campaign bindings. Exclusions contain safe reason codes without phone numbers or full contact payloads. Failed and partial runs are never active.

Finalization rechecks campaign version and payload hash, reconciles counts, enforces the recipient subscription limit, and binds only a completed run. Zero eligible recipients moves the campaign to needs-attention. Current consent must be checked again immediately before Part 3 sends.

Cancellation stops future chunks and returns the campaign to ready. Invalidation marks the completed run stale, removes its active snapshot rows, resets counters, and returns prepared campaigns to ready. Reconciliation marks stuck or mismatched runs without launching campaigns.

Part 3 may consume recipients only when the campaign is `prepared`, its active preparation is `completed`, and both version and payload hash still match.
