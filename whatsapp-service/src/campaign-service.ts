import crypto from 'node:crypto';
import type { CallbackClient } from './callback-client.js';
import type { Config } from './config.js';
import { CampaignStore } from './campaign-store.js';
import type { CampaignDispatch, CampaignDispatchResult, CampaignFailure } from './campaign-types.js';
import { validateCampaignDispatch } from './campaign-types.js';
import type { SessionManager } from './session-manager.js';

export class CampaignService {
  readonly store: CampaignStore;
  private callbackTimer?: NodeJS.Timeout;
  constructor(private readonly config: Config, private readonly sessions: SessionManager, private readonly callbacks: CallbackClient) {
    this.store = new CampaignStore(config.campaign.storePath, config.campaign.idempotencyTtlMs);
    sessions.onCampaignAcknowledgement((key, id, ack) => this.acknowledge(key, id, ack));
    this.callbackTimer = setInterval(() => void this.flushOutbox(), 2_000); this.callbackTimer.unref();
  }

  async dispatch(raw: unknown, requestId: string, headerIdempotencyKey: string): Promise<CampaignDispatchResult> {
    if (!this.config.campaign.enabled) return this.failure(requestId, raw, 'transport', 'transport_rejected', true);
    const input = validateCampaignDispatch(raw);
    if (headerIdempotencyKey !== input.idempotency_key) throw new Error('idempotency_conflict');
    const requestHash = input.transport_request_hash;
    const existing = this.store.getByKey(input.idempotency_key);
    if (existing) {
      if (existing.requestHash !== requestHash || existing.dispatchAttemptUuid !== input.dispatch_attempt_uuid) throw new Error('idempotency_conflict');
      return { ...existing.result, request_id: requestId, duplicate: true };
    }
    const transportReference = crypto.randomUUID();
    let result: CampaignDispatchResult = {
      success: false, request_id: requestId, idempotency_key: input.idempotency_key,
      dispatch_attempt_uuid: input.dispatch_attempt_uuid, status: 'sending', transport_reference: transportReference,
      whatsapp_message_id: null, duplicate: false, failure: null, server_time: new Date().toISOString(),
    };
    this.store.put({ requestHash, tenantUuid: input.tenant_uuid, sessionUuid: input.session_uuid, dispatchAttemptUuid: input.dispatch_attempt_uuid, idempotencyKey: input.idempotency_key, status: 'sending', result, updatedAt: Date.now() });
    try {
      const messageId = await withTimeout(this.sessions.sendCampaign({
        tenantUuid: input.tenant_uuid, sessionUuid: input.session_uuid, recipientAddress: input.recipient.whatsapp_address,
        type: input.message.type, body: input.message.caption ?? input.message.body, attachment: input.message.attachment,
        campaignKey: input.idempotency_key,
      }), this.config.campaign.sendTimeoutMs);
      result = { ...result, success: true, status: 'sent', whatsapp_message_id: messageId, server_time: new Date().toISOString() };
      this.store.put({ requestHash, tenantUuid: input.tenant_uuid, sessionUuid: input.session_uuid, dispatchAttemptUuid: input.dispatch_attempt_uuid, idempotencyKey: input.idempotency_key, status: 'sent', result, updatedAt: Date.now(), whatsappMessageId: messageId });
      this.store.correlate(messageId, input.idempotency_key);
      this.publish(input, result, 'campaign.transport.sent');
      return result;
    } catch (error) {
      const failure = classify(error);
      const status = failure.code === 'transport_state_unknown' ? 'unknown' : 'failed';
      result = { ...result, success: false, status, failure, server_time: new Date().toISOString() };
      this.store.put({ requestHash, tenantUuid: input.tenant_uuid, sessionUuid: input.session_uuid, dispatchAttemptUuid: input.dispatch_attempt_uuid, idempotencyKey: input.idempotency_key, status, result, updatedAt: Date.now() });
      this.publish(input, result, status === 'unknown' ? 'campaign.transport.unknown' : 'campaign.transport.failed');
      return result;
    }
  }

  lookup(attemptUuid: string, tenantUuid: string, idempotencyKey: string): CampaignDispatchResult | undefined {
    const record = this.store.getByAttempt(attemptUuid);
    if (!record || record.tenantUuid !== tenantUuid || record.idempotencyKey !== idempotencyKey) return undefined;
    return record.result;
  }
  health(): Record<string, unknown> { return { enabled: this.config.campaign.enabled, registry: 'available', outbox: 'available', ...this.store.counts() }; }
  stop(): void { if (this.callbackTimer) clearInterval(this.callbackTimer); }

  private acknowledge(key: string, messageId: string, ack: number): void {
    const record = this.store.getByKey(key); if (!record || record.whatsappMessageId !== messageId) return;
    const type = ack >= 3 ? 'campaign.message.read' : ack >= 2 ? 'campaign.message.delivered' : null;
    if (!type) return;
    const eventId = crypto.createHash('sha256').update(`${record.dispatchAttemptUuid}|${type}`).digest('hex');
    this.enqueue(eventId, {
      event_id: eventId, event_type: type, occurred_at: new Date().toISOString(),
      tenant_uuid: record.tenantUuid, dispatch_attempt_uuid: record.dispatchAttemptUuid,
      session_uuid: record.sessionUuid, idempotency_key: record.idempotencyKey,
      transport_reference: record.result.transport_reference, whatsapp_message_id: messageId, ack_code: ack,
    });
  }
  private publish(input: CampaignDispatch, result: CampaignDispatchResult, type: string): void {
    const eventId = crypto.createHash('sha256').update(`${input.dispatch_attempt_uuid}|${type}|${result.status}`).digest('hex');
    this.enqueue(eventId, {
      event_id: eventId, event_type: type, occurred_at: new Date().toISOString(), tenant_uuid: input.tenant_uuid,
      campaign_uuid: input.campaign_uuid, execution_uuid: input.execution_uuid, recipient_execution_uuid: input.recipient_execution_uuid,
      dispatch_attempt_uuid: input.dispatch_attempt_uuid, session_uuid: input.session_uuid, idempotency_key: input.idempotency_key,
      transport_reference: result.transport_reference, whatsapp_message_id: result.whatsapp_message_id, failure: result.failure,
    });
  }
  private enqueue(eventId: string, payload: Record<string, unknown>): void { this.store.enqueue(eventId, payload, Date.now() + this.config.campaign.correlationRetentionMs); void this.flushOutbox(); }
  private async flushOutbox(): Promise<void> {
    for (const event of this.store.dueOutbox().slice(0, 20)) {
      try { await this.callbacks.emitCampaign(event.payload); this.store.delivered(event.eventId); }
      catch {
        if (event.attempts + 1 >= this.config.campaign.callbackMaxAttempts) {
          this.store.retry(event.eventId, this.config.campaign.correlationRetentionMs);
          continue;
        }
        const delay = Math.min(this.config.campaign.callbackMaxDelayMs, this.config.campaign.callbackBaseDelayMs * 2 ** event.attempts);
        this.store.retry(event.eventId, delay);
      }
    }
  }
  private failure(requestId: string, raw: unknown, klass: string, code: string, retryable: boolean): CampaignDispatchResult {
    const input = raw as Partial<CampaignDispatch>;
    return { success: false, request_id: requestId, idempotency_key: String(input.idempotency_key ?? ''), dispatch_attempt_uuid: String(input.dispatch_attempt_uuid ?? ''), status: 'failed', transport_reference: null, whatsapp_message_id: null, duplicate: false, failure: { class: klass, code, retryable, message: safeMessage(code) }, server_time: new Date().toISOString() };
  }
}

function classify(error: unknown): CampaignFailure {
  const code = error instanceof Error ? error.message : 'internal_error';
  const session = code.startsWith('session_');
  const attachment = code.startsWith('attachment_');
  const retryable = ['session_disconnected', 'session_reconnecting', 'session_capacity_exceeded', 'attachment_download_failed', 'transport_state_unknown'].includes(code);
  return { class: session ? 'session' : attachment ? 'attachment' : code === 'transport_state_unknown' ? 'unknown' : 'transport', code, retryable, message: safeMessage(code) };
}
function safeMessage(code: string): string {
  if (code.startsWith('session_')) return 'The selected WhatsApp session is unavailable.';
  if (code.startsWith('attachment_')) return 'The campaign attachment could not be verified.';
  return 'The connector could not confirm the campaign dispatch.';
}
async function withTimeout<T>(promise: Promise<T>, timeoutMs: number): Promise<T> {
  let timer: NodeJS.Timeout | undefined;
  try { return await Promise.race([promise, new Promise<T>((_, reject) => { timer = setTimeout(() => reject(new Error('transport_state_unknown')), timeoutMs); })]); }
  finally { if (timer) clearTimeout(timer); }
}
