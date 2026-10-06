import fs from 'node:fs';
import path from 'node:path';

export type CallbackKind = 'session' | 'message' | 'inbox';

export type CallbackRecord = {
  id: string;
  kind: CallbackKind;
  target: string;
  payload: Record<string, unknown>;
  attempts: number;
  nextAttemptAt: number;
  expiresAt: number;
};

type State = { records: Record<string, CallbackRecord> };

export class CallbackOutbox {
  private state: State = { records: {} };

  constructor(private readonly filename: string) {
    fs.mkdirSync(path.dirname(filename), { recursive: true, mode: 0o700 });
    try {
      this.state = JSON.parse(fs.readFileSync(filename, 'utf8')) as State;
    } catch {
      this.persist();
    }
    this.cleanup();
  }

  put(record: CallbackRecord): void {
    this.state.records[record.id] ??= record;
    this.persist();
  }

  get(id: string): CallbackRecord | undefined {
    return this.state.records[id];
  }

  due(now = Date.now()): CallbackRecord[] {
    return Object.values(this.state.records)
      .filter((record) => record.nextAttemptAt <= now && record.expiresAt > now)
      .sort((a, b) => a.nextAttemptAt - b.nextAttemptAt);
  }

  delivered(id: string): void {
    delete this.state.records[id];
    this.persist();
  }

  retry(id: string, delayMs: number): void {
    const record = this.state.records[id];
    if (!record) return;
    record.attempts += 1;
    record.nextAttemptAt = Date.now() + delayMs;
    this.persist();
  }

  count(): number {
    return Object.keys(this.state.records).length;
  }

  cleanup(now = Date.now()): void {
    let changed = false;
    for (const [id, record] of Object.entries(this.state.records)) {
      if (record.expiresAt <= now) {
        delete this.state.records[id];
        changed = true;
      }
    }
    if (changed) this.persist();
  }

  private persist(): void {
    const temporary = `${this.filename}.${process.pid}.tmp`;
    fs.writeFileSync(temporary, JSON.stringify(this.state), { mode: 0o600 });
    fs.renameSync(temporary, this.filename);
  }
}
