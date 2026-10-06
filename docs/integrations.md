# Integrations

Tenant integrations support Generic REST/Laravel ERP, outbound HTTPS webhooks, WordPress-compatible REST calls, and WooCommerce webhooks. Authenticate API requests with the one-time Bearer token, or sign `timestamp.raw_body` using HMAC-SHA256 in `X-Webhook-Timestamp` and `X-Webhook-Signature`. WooCommerce's `X-WC-Webhook-Signature` is also accepted. Reusing an external event ID or message idempotency key safely returns the existing result.

`POST /api/integrations/{uuid}/messages` queues either bounded plain text or a published message template through canonical transactional messaging and usage accounting. `POST /api/integrations/{uuid}/events` accepts the documented ERP, WordPress, and WooCommerce event set. WooCommerce order/payment events may map directly to frozen template versions using customer name, phone, order number, total, currency, and status variables. WordPress can send user, form, custom, or direct-message calls through the same endpoints; arbitrary form mapping is deferred.

Outbound webhooks are HTTPS-only, HMAC signed, payload-bounded, encrypted in queued jobs, and protected from loopback/private/link-local targets. Delivery records contain hashes and safe status only. Credentials use Laravel encrypted casting, are masked in tenant UI, and never appear in platform views. Shopify is an extension placeholder only; OAuth installation, deeper automation webhook actions, CRM/accounting providers, dedicated Zapier/Make apps, marketplace SDKs, and automated tests are deferred until future scope and final QA.


## Developer messaging API

External systems such as ERPs, POS applications, accounting systems, CRMs and ecommerce sites can send transactional WhatsApp messages through a tenant integration.

Create a Generic API integration in the tenant workspace, copy the one-time Bearer token, and use the integration UUID in API calls.

```http
POST /api/integrations/{integration_uuid}/messages
Authorization: Bearer {token}
Content-Type: application/json
```

```json
{
  "recipient": "+93700000000",
  "text": "Dear customer, your current balance is 12,500 AFN.",
  "idempotency_key": "balance-notice-2026-10-06-0001"
}
```

The API returns HTTP 202 with a durable message UUID and the current queue state. Reusing the same idempotency key with identical content returns the existing message instead of creating a duplicate.

Poll delivery state with:

```http
GET /api/integrations/{integration_uuid}/messages/{message_uuid}
Authorization: Bearer {token}
```

When a HTTPS callback URL is configured, Wasender also signs and delivers message lifecycle webhooks for `message.queued`, `message.sent`, `message.delivered`, `message.read` and `message.failed`. Callback bodies are authenticated with the integration webhook secret using `X-Webhook-Timestamp`, `X-Webhook-Id` and `X-Webhook-Signature`.

The same API path uses the tenant's canonical WhatsApp queue, session pacing, idempotency, entitlement checks and uncertain-send reconciliation. External applications never connect directly to Chromium or the WhatsApp connector.
