import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

export type SendResult = { accepted: boolean; whatsapp_message_id?: string };

export type SendRecord = {
  hash: string;
  messageUuid: string;
  requestId: string;
  status: 'sending' | 'sent' | 'failed' | 'unknown';
  result?: SendResult;
  error?: string;
  updatedAt: number;
};

type RegistryState = { records: Record<string, SendRecord> };

export class RequestRegistry {
  private state: RegistryState = { records: {} };

  constructor(
    private readonly filename?: string,
    private readonly ttlMs = 7 * 24 * 60 * 60 * 1000,
    private readonly max = 10_000,
  ) {
    if (!filename) return;

    fs.mkdirSync(path.dirname(filename), { recursive: true });
    try {
      this.state = JSON.parse(fs.readFileSync(filename, 'utf8')) as RegistryState;
    } catch {
      this.persist();
    }

    for (const record of Object.values(this.state.records)) {
      if (record.status === 'sending') {
        record.status = 'unknown';
        record.error = 'request_outcome_unknown';
        record.updatedAt = Date.now();
      }
    }

    this.cleanup();
  }

  begin(requestId: string, payload: unknown, messageUuid: string): { record: SendRecord; duplicate: boolean } {
    this.cleanup();

    const hash = crypto.createHash('sha256').update(JSON.stringify(payload)).digest('hex');
    const existing = this.state.records[requestId];

    if (existing) {
      if (existing.hash !== hash) throw new Error('request_id_payload_mismatch');
      return { record: existing, duplicate: true };
    }

    const records = Object.values(this.state.records);
    if (records.length >= this.max) {
      const evictable = records
        .filter((record) => !['sending', 'unknown'].includes(record.status))
        .sort((a, b) => a.updatedAt - b.updatedAt);

      if (evictable.length === 0) throw new Error('request_registry_capacity_exceeded');
      delete this.state.records[evictable[0].requestId];
    }

    const record: SendRecord = {
      hash,
      messageUuid,
      requestId,
      status: 'sending',
      updatedAt: Date.now(),
    };

    this.state.records[requestId] = record;
    this.persist();

    return { record, duplicate: false };
  }

  complete(requestId: string, result: SendResult): SendRecord {
    const record = this.require(requestId);
    record.status = 'sent';
    record.result = result;
    record.error = undefined;
    record.updatedAt = Date.now();
    this.persist();

    return record;
  }

  fail(requestId: string, error: string): SendRecord {
    const record = this.require(requestId);
    record.status = 'failed';
    record.error = error;
    record.updatedAt = Date.now();
    this.persist();

    return record;
  }

  unknown(requestId: string): SendRecord {
    const record = this.require(requestId);
    record.status = 'unknown';
    record.error = 'request_outcome_unknown';
    record.updatedAt = Date.now();
    this.persist();

    return record;
  }

  get(requestId: string): SendRecord | undefined {
    this.cleanup();
    return this.state.records[requestId];
  }

  cleanup(now = Date.now()): void {
    let changed = false;
    for (const [key, record] of Object.entries(this.state.records)) {
      if (record.updatedAt + this.ttlMs < now) {
        delete this.state.records[key];
        changed = true;
      }
    }
    if (changed) this.persist();
  }

  private require(requestId: string): SendRecord {
    const record = this.state.records[requestId];
    if (!record) throw new Error('request_not_found');
    return record;
  }

  private persist(): void {
    if (!this.filename) return;

    const temporary = `${this.filename}.${process.pid}.tmp`;
    fs.writeFileSync(temporary, JSON.stringify(this.state), { mode: 0o600 });
    fs.renameSync(temporary, this.filename);
  }
}
