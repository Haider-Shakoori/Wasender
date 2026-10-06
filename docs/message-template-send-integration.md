# Message template send integration

Batch 17 Part 4 connects immutable published template versions to campaigns and transactional WhatsApp messages.

## Snapshot and retry contract

- A send stores the public template UUID, published version UUID and number, content hash, render hash, resolved values, and render timestamp.
- Foreign keys are nullable for lifecycle safety, while the public identifiers and hashes remain after archival or deletion.
- Transactional template sends delegate to the existing `WhatsAppMessageService` and `DispatchWhatsAppMessage` job. The connector protocol is unchanged.
- Campaign preparation renders once per eligible contact and stores `rendered_body`, `rendered_caption`, and `template_render_hash` on the recipient snapshot.
- Campaign dispatch reads the frozen recipient snapshot. Retries never re-render from live template or contact data.
- A recipient whose required variables cannot be rendered is excluded with `template_render_failed`; the preparation job continues safely.

## Media and privacy

Template media remains on private storage. Campaign and transactional attachment records reference the same immutable storage object and copy only verified metadata, not file bytes. Existing signed/internal attachment delivery remains authoritative.

Resolved values are stored only where required for transactional reproducibility. Campaign-level overrides are stored on the campaign; contact-derived values stay inside recipient rendering and are not written to campaign metadata or audit logs.

## HTTP endpoints

- `POST /app/messages/from-template`
- `POST /app/campaigns/{campaign}/template`
- `DELETE /app/campaigns/{campaign}/template`

All endpoints require tenant context, existing send/campaign permissions, the `whatsapp_templates.use` permission, subscription access where applicable, and the template feature entitlement. Campaign mutations use optimistic `expected_version` checks and reject prepared or otherwise non-editable campaigns.

Manual campaign content updates explicitly detach template attribution. Campaign duplication copies the stored template snapshot rather than resolving the current live version.
