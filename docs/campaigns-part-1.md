# WhatsApp campaigns — Batch 13 Part 1

`WhatsAppCampaign` is a tenant-owned aggregate. Its content and execution preferences live on `whatsapp_campaigns`; private media, selected sessions, audience UUID references, append-only events, and mutation idempotency are separate tables.

The lifecycle defines draft, validation, ready/scheduled, future execution, terminal, and archived states. Only draft, needs-attention, and ready are editable. A scheduled campaign must be unscheduled before editing. All transitions use row locking and expected versions.

Audience sources are all eligible contacts, one segment, groups, labels, or explicitly selected contact UUIDs. Phone numbers and resolved audiences are never stored in campaign configuration. Session strategies are single, selected pool, and automatic pool; all selected sessions are tenant verified.

Scheduling preserves the IANA timezone, local input, and canonical UTC value. Part 1 stores send-window shape and conservative retry/consent preferences but does not execute them.

Payload hashes use recursively key-sorted canonical JSON, sort order-insensitive lists, and include content, attachment checksum, audience, session, schedule, window, and execution configuration. Material updates increment `version`; stale expected versions fail.

Tenant permissions cover view, create, update, duplicate, schedule, archive, content, and events, with future execution permissions registered but inactive. Subscription features are `campaigns.manage` and `campaigns.schedule`; campaign, active, monthly, recipient, manual-contact, and session limits are registered.

Attachments use server-detected allowlisted MIME types, internally generated tenant/campaign storage keys, SHA-256 checksums, private storage, authenticated tenant downloads, no-store headers, and no platform download route.

Campaign events contain safe lifecycle metadata only. Platform visibility is read-only and redacts content, audience configuration, recipients, attachments, and credentials.

Part 1 does not resolve recipients, create snapshots or message rows, dispatch jobs, call Node, throttle, retry delivery, aggregate progress, or expose public APIs. Part 2 can implement immutable recipient snapshots through `CampaignRecipientEligibility`; Part 3 can consume snapshots and the stored execution/window configuration after verifying the payload hash.
