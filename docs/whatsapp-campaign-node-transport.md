# WhatsApp campaign Node transport

Batch 13 Part 4 replaces the non-sending transport with `NodeWhatsAppCampaignTransport`. Laravel derives requests only from
prepared recipient executions and posts to the internal HMAC-protected `POST /internal/v1/campaign-messages/send` route.
The strict v1 schema carries tenant/campaign/execution/attempt/session UUID correlation, a direct `@c.us` address, bounded
text or media metadata, hashes, and the per-attempt idempotency key. Group, broadcast, status, and arbitrary chat IDs fail.

Node validates session ownership/readiness, enforces defensive per-session capacity, sends through `whatsapp-web.js`, and
uses its returned serialized message ID as the authoritative sent boundary. Dispatch state, message correlation, replay
outcomes, and callback outbox data survive restart. Records interrupted in `sending` become `unknown` and are never resent
automatically. `GET /internal/v1/campaign-dispatches/{uuid}` resolves the same attempt during reconciliation.

Supported message types are text, image, document, audio, and video, with captions where supported. No public campaign API,
arbitrary-number transport, group send, proxy rotation, fingerprint spoofing, or ban-evasion feature exists.
