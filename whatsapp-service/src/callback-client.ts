import crypto from 'node:crypto';
import { signature } from './security.js';

export class CallbackClient {
  constructor(
    private readonly url: string,
    private readonly secret: string,
    private readonly timeoutMs: number,
  ) {}

  async emit(sessionUuid: string, event: string, payload: Record<string, unknown> = {}): Promise<void> {
    const body = { event_id: crypto.randomUUID(), reference: sessionUuid, event, occurred_at: new Date().toISOString(), ...payload };
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomUUID();
    const route = new URL(this.url).pathname;
    const response = await fetch(this.url, {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-internal-source': 'whatsapp-connector',
        'x-internal-timestamp': timestamp,
        'x-internal-nonce': nonce,
        'x-internal-content-sha256': crypto.createHash('sha256').update(JSON.stringify(body)).digest('hex'),
        'x-internal-signature': signature(this.secret, 'POST', route, timestamp, nonce, body),
      },
      body: JSON.stringify(body),
      signal: AbortSignal.timeout(this.timeoutMs),
    });
    if (!response.ok) throw new Error(`callback_failed_${response.status}`);
  }

  async emitMessage(messageUuid: string, requestId: string, event: string, payload: Record<string, unknown> = {}): Promise<void> {
    const target = this.url.replace(/\/events$/, '/message-events');
    const body = { event_id: crypto.randomUUID(), message_uuid: messageUuid, request_id: requestId, event, ...payload };
    const timestamp = Math.floor(Date.now() / 1000).toString(); const nonce = crypto.randomUUID();
    const route = new URL(target).pathname;
    const response = await fetch(target, { method: 'POST', headers: { 'content-type': 'application/json', 'x-internal-source': 'whatsapp-connector', 'x-internal-timestamp': timestamp,
      'x-internal-nonce': nonce, 'x-internal-signature': signature(this.secret, 'POST', route, timestamp, nonce, body) },
      body: JSON.stringify(body), signal: AbortSignal.timeout(this.timeoutMs) });
    if (!response.ok) throw new Error(`message_callback_failed_${response.status}`);
  }

  async emitCampaign(payload: Record<string, unknown>): Promise<void> {
    const target = this.url.replace(/\/events$/, '/campaign-events');
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomUUID();
    const requestId = String(payload.event_id);
    const route = new URL(target).pathname;
    const response = await fetch(target, {
      method: 'POST',
      headers: {
        'content-type': 'application/json',
        'x-internal-source': 'whatsapp-connector',
        'x-internal-timestamp': timestamp,
        'x-internal-nonce': nonce,
        'x-internal-request-id': requestId,
        'x-internal-idempotency-key': requestId,
        'x-internal-content-sha256': crypto.createHash('sha256').update(JSON.stringify(payload)).digest('hex'),
        'x-internal-signature': signature(this.secret, 'POST', route, timestamp, nonce, payload, requestId, requestId),
      },
      body: JSON.stringify(payload),
      signal: AbortSignal.timeout(this.timeoutMs),
    });
    if (!response.ok) throw new Error(`campaign_callback_failed_${response.status}`);
  }

  async emitInbox(payload: Record<string, unknown>): Promise<void> {
    const target = this.url.replace(/\/events$/, '/inbox-events');
    for (let attempt = 1; attempt <= 3; attempt++) {
      const timestamp = Math.floor(Date.now() / 1000).toString();
      const nonce = crypto.randomUUID();
      const requestId = String(payload.event_id);
      const route = new URL(target).pathname;
      const body = JSON.stringify(payload);
      const response = await fetch(target, { method: 'POST', headers: {
        'content-type': 'application/json', 'x-internal-source': 'whatsapp-connector', 'x-internal-timestamp': timestamp, 'x-internal-nonce': nonce,
        'x-internal-request-id': requestId, 'x-internal-idempotency-key': requestId, 'x-internal-content-sha256': crypto.createHash('sha256').update(body).digest('hex'),
        'x-internal-signature': signature(this.secret, 'POST', route, timestamp, nonce, payload, requestId, requestId),
      }, body, signal: AbortSignal.timeout(this.timeoutMs) });
      if (response.ok) return;
      if (attempt === 3 || response.status < 500) throw new Error(`inbox_callback_failed_${response.status}`);
      await new Promise((resolve) => setTimeout(resolve, attempt * 250));
    }
  }
}
