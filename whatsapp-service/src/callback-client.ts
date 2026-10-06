import crypto from 'node:crypto';
import { signature } from './security.js';
import { CallbackOutbox, type CallbackRecord } from './callback-outbox.js';

export class CallbackClient {
  private readonly outbox?: CallbackOutbox;
  private readonly timer?: NodeJS.Timeout;

  constructor(
    private readonly url: string,
    private readonly secret: string,
    private readonly timeoutMs: number,
    outboxPath?: string,
  ) {
    if (outboxPath) {
      this.outbox = new CallbackOutbox(outboxPath);
      this.timer = setInterval(() => void this.flush(), 2_000);
      this.timer.unref();
      void this.flush();
    }
  }

  async emit(sessionUuid: string, event: string, payload: Record<string, unknown> = {}): Promise<void> {
    const body = { event_id: crypto.randomUUID(), reference: sessionUuid, event, occurred_at: new Date().toISOString(), ...payload };
    await this.enqueueAndDeliver('session', this.url, body);
  }

  async emitMessage(messageUuid: string, requestId: string, event: string, payload: Record<string, unknown> = {}): Promise<void> {
    const target = this.url.replace(/\/events$/, '/message-events');
    const body = { event_id: crypto.randomUUID(), message_uuid: messageUuid, request_id: requestId, event, ...payload };
    await this.enqueueAndDeliver('message', target, body);
  }

  async emitInbox(payload: Record<string, unknown>): Promise<void> {
    const target = this.url.replace(/\/events$/, '/inbox-events');
    await this.enqueueAndDeliver('inbox', target, payload);
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

  health(): { callback_backlog: number } {
    return { callback_backlog: this.outbox?.count() ?? 0 };
  }

  private async enqueueAndDeliver(kind: 'session' | 'message' | 'inbox', target: string, payload: Record<string, unknown>): Promise<void> {
    const id = String(payload.event_id ?? crypto.randomUUID());
    const record: CallbackRecord = {
      id,
      kind,
      target,
      payload: { ...payload, event_id: id },
      attempts: 0,
      nextAttemptAt: Date.now(),
      expiresAt: Date.now() + 7 * 24 * 60 * 60 * 1000,
    };

    if (!this.outbox) {
      await this.deliver(record);
      return;
    }

    this.outbox.put(record);
    await this.deliverRecord(record);
  }

  private async flush(): Promise<void> {
    if (!this.outbox) return;
    for (const record of this.outbox.due().slice(0, 50)) {
      await this.deliverRecord(record);
    }
  }

  private async deliverRecord(record: CallbackRecord): Promise<void> {
    if (!this.outbox) return;
    try {
      await this.deliver(record);
      this.outbox.delivered(record.id);
    } catch (error) {
      const delay = Math.min(15 * 60_000, 1000 * 2 ** Math.min(record.attempts, 10));
      this.outbox.retry(record.id, delay);
      throw error;
    }
  }

  private async deliver(record: CallbackRecord): Promise<void> {
    const timestamp = Math.floor(Date.now() / 1000).toString();
    const nonce = crypto.randomUUID();
    const route = new URL(record.target).pathname;
    const requestId = record.kind === 'inbox' ? record.id : undefined;
    const idempotencyKey = requestId;
    const body = JSON.stringify(record.payload);
    const headers: Record<string, string> = {
      'content-type': 'application/json',
      'x-internal-source': 'whatsapp-connector',
      'x-internal-timestamp': timestamp,
      'x-internal-nonce': nonce,
      'x-internal-content-sha256': crypto.createHash('sha256').update(body).digest('hex'),
    };
    if (requestId) {
      headers['x-internal-request-id'] = requestId;
      headers['x-internal-idempotency-key'] = requestId;
    }
    headers['x-internal-signature'] = signature(this.secret, 'POST', route, timestamp, nonce, record.payload, requestId, idempotencyKey);

    const response = await fetch(record.target, {
      method: 'POST',
      headers,
      body,
      signal: AbortSignal.timeout(this.timeoutMs),
    });

    if (!response.ok) throw new Error(`${record.kind}_callback_failed_${response.status}`);
  }
}
