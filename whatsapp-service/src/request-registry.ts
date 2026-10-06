import crypto from 'node:crypto';

export type SendRecord = { hash: string; result?: { accepted: boolean; whatsapp_message_id?: string }; messageUuid: string; requestId: string };

export class RequestRegistry {
  private readonly records = new Map<string, SendRecord>();
  constructor(private readonly max = 10_000) {}
  begin(requestId: string, payload: unknown, messageUuid: string): { record: SendRecord; duplicate: boolean } {
    const hash = crypto.createHash('sha256').update(JSON.stringify(payload)).digest('hex');
    const existing = this.records.get(requestId);
    if (existing) {
      if (existing.hash !== hash) throw new Error('request_id_payload_mismatch');
      return { record: existing, duplicate: true };
    }
    if (this.records.size >= this.max) this.records.delete(this.records.keys().next().value as string);
    const record = { hash, messageUuid, requestId }; this.records.set(requestId, record);
    return { record, duplicate: false };
  }
  get(requestId: string): SendRecord | undefined { return this.records.get(requestId); }
}
