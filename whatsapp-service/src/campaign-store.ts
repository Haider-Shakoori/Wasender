import fs from 'node:fs';
import path from 'node:path';
import type { CampaignDispatchResult } from './campaign-types.js';

type DispatchRecord = {
  requestHash: string; tenantUuid: string; sessionUuid: string; dispatchAttemptUuid: string;
  idempotencyKey: string; status: CampaignDispatchResult['status']; result: CampaignDispatchResult;
  updatedAt: number; whatsappMessageId?: string;
};
type OutboxRecord = { eventId: string; payload: Record<string, unknown>; attempts: number; nextAttemptAt: number; expiresAt: number };
type State = { dispatches: Record<string, DispatchRecord>; correlations: Record<string, string>; outbox: Record<string, OutboxRecord> };

export class CampaignStore {
  private state: State = { dispatches: {}, correlations: {}, outbox: {} };
  constructor(private readonly filename: string, private readonly ttlMs: number) {
    fs.mkdirSync(path.dirname(filename), { recursive: true });
    try { this.state = JSON.parse(fs.readFileSync(filename, 'utf8')) as State; } catch { this.persist(); }
    for (const record of Object.values(this.state.dispatches)) {
      if (record.status === 'sending') { record.status = 'unknown'; record.result.status = 'unknown'; }
    }
    this.cleanup();
  }
  getByKey(key: string): DispatchRecord | undefined { return this.state.dispatches[key]; }
  getByAttempt(uuid: string): DispatchRecord | undefined { return Object.values(this.state.dispatches).find((r) => r.dispatchAttemptUuid === uuid); }
  put(record: DispatchRecord): void { this.state.dispatches[record.idempotencyKey] = record; this.persist(); }
  correlate(messageId: string, key: string): void { this.state.correlations[messageId] = key; this.persist(); }
  correlated(messageId: string): DispatchRecord | undefined { const key = this.state.correlations[messageId]; return key ? this.state.dispatches[key] : undefined; }
  enqueue(eventId: string, payload: Record<string, unknown>, expiresAt: number): void {
    this.state.outbox[eventId] ??= { eventId, payload, attempts: 0, nextAttemptAt: Date.now(), expiresAt }; this.persist();
  }
  dueOutbox(now = Date.now()): OutboxRecord[] { return Object.values(this.state.outbox).filter((e) => e.nextAttemptAt <= now && e.expiresAt > now); }
  delivered(eventId: string): void { delete this.state.outbox[eventId]; this.persist(); }
  retry(eventId: string, delayMs: number): void { const event = this.state.outbox[eventId]; if (event) { event.attempts++; event.nextAttemptAt = Date.now() + delayMs; this.persist(); } }
  counts(): { dispatches: number; unknown: number; callbackBacklog: number } {
    const values = Object.values(this.state.dispatches); return { dispatches: values.length, unknown: values.filter((r) => r.status === 'unknown').length, callbackBacklog: Object.keys(this.state.outbox).length };
  }
  cleanup(now = Date.now()): void {
    for (const [key, record] of Object.entries(this.state.dispatches)) if (record.updatedAt + this.ttlMs < now) delete this.state.dispatches[key];
    for (const [key, event] of Object.entries(this.state.outbox)) if (event.expiresAt <= now) delete this.state.outbox[key];
    this.persist();
  }
  private persist(): void {
    const temporary = `${this.filename}.${process.pid}.tmp`;
    fs.writeFileSync(temporary, JSON.stringify(this.state), { mode: 0o600 });
    fs.renameSync(temporary, this.filename);
  }
}
