import { fork, type ChildProcess } from 'node:child_process';
import crypto from 'node:crypto';
import { fileURLToPath } from 'node:url';
import type { CallbackClient } from './callback-client.js';
import type { Config } from './config.js';
import type { CampaignSendInput, DirectSendInput, SessionInput, SessionRuntime } from './session-runtime.js';
import type { WorkerMessage, WorkerRequest } from './session-worker-protocol.js';

type Pending = {
  resolve: (value: unknown) => void;
  reject: (error: Error) => void;
  timer: NodeJS.Timeout;
};

type WorkerRecord = {
  child: ChildProcess;
  input: SessionInput;
  state: string;
  intentionalShutdown: boolean;
  pending: Map<string, Pending>;
};

export class SessionSupervisor implements SessionRuntime {
  private readonly workers = new Map<string, WorkerRecord>();
  private readonly crashHistory = new Map<string, number[]>();
  private readonly restartTimers = new Map<string, NodeJS.Timeout>();
  private readonly crashLoops = new Set<string>();
  private campaignAcknowledgement?: (campaignKey: string, messageId: string, ack: number) => void;

  constructor(
    private readonly config: Config,
    private readonly callbacks: CallbackClient,
  ) {}

  async initialize(input: SessionInput): Promise<void> {
    if (this.workers.has(input.session_uuid)) return;
    this.crashLoops.delete(input.session_uuid);
    const record = this.spawn(input);
    await this.request(record, 'initialize', input);
  }

  status(uuid: string): Record<string, unknown> | null {
    const record = this.workers.get(uuid);
    if (record) {
      return {
        reference: uuid,
        status: record.state,
        metadata: {
          isolated_worker: true,
          worker_pid: record.child.pid ?? null,
        },
      };
    }

    if (this.restartTimers.has(uuid)) {
      return { reference: uuid, status: 'reconnecting', metadata: { isolated_worker: true, restarting: true } };
    }

    if (this.crashLoops.has(uuid)) {
      return { reference: uuid, status: 'failed', metadata: { isolated_worker: true, crash_loop: true } };
    }

    return null;
  }

  async restart(uuid: string, fallback?: Pick<SessionInput, 'storage_key' | 'tenant_uuid'>): Promise<void> {
    const record = this.workers.get(uuid);
    if (record) {
      record.state = 'reconnecting';
      await this.request(record, 'restart', { uuid, fallback });
      return;
    }

    const storageKey = fallback?.storage_key ?? '';
    const input: SessionInput = { session_uuid: uuid, storage_key: storageKey, tenant_uuid: fallback?.tenant_uuid };
    await this.initialize(input);
  }

  async disconnect(uuid: string): Promise<void> {
    this.cancelRestart(uuid);
    const record = this.workers.get(uuid);
    if (!record) return;

    record.intentionalShutdown = true;
    try {
      await this.request(record, 'disconnect', uuid);
    } finally {
      await this.stop(record);
    }
  }

  async logout(uuid: string): Promise<void> {
    this.cancelRestart(uuid);
    const record = this.workers.get(uuid);
    if (!record) return;

    record.intentionalShutdown = true;
    try {
      await this.request(record, 'logout', uuid);
    } finally {
      await this.stop(record);
    }
  }

  async remove(input: SessionInput): Promise<void> {
    this.cancelRestart(input.session_uuid);
    let record = this.workers.get(input.session_uuid);
    let temporary = false;

    if (!record) {
      record = this.spawn(input);
      temporary = true;
    }

    record.intentionalShutdown = true;
    try {
      await this.request(record, 'remove', input);
    } finally {
      if (temporary || this.workers.has(input.session_uuid)) await this.stop(record);
    }
  }

  async heartbeat(): Promise<void> {
    await Promise.all([...this.workers.values()].map(async (record) => {
      try {
        await this.request(record, 'heartbeat');
      } catch {
        if (!record.intentionalShutdown) record.child.kill('SIGKILL');
      }
    }));
  }

  health(): Record<string, unknown> {
    const states = [...this.workers.values()].map((worker) => worker.state);

    const memory = process.memoryUsage();

    return {
      isolation: 'process',
      uptime_seconds: Math.floor(process.uptime()),
      node_version: process.version,
      memory_rss_mb: Math.round(memory.rss / 1_048_576),
      memory_heap_used_mb: Math.round(memory.heapUsed / 1_048_576),
      owned_sessions: this.workers.size,
      ready_sessions: states.filter((state) => state === 'ready').length,
      reconnecting_sessions: states.filter((state) => state === 'reconnecting').length + this.restartTimers.size,
      crash_loop_sessions: this.crashLoops.size,
      worker_restart_limit: this.config.sessionWorkers.maxRestarts,
      worker_restart_window_seconds: Math.round(this.config.sessionWorkers.restartWindowMs / 1000),
      worker_processes: [...this.workers.values()].map((worker) => ({
        session_uuid: worker.input.session_uuid,
        pid: worker.child.pid ?? null,
        state: worker.state,
      })),
    };
  }

  async send(input: DirectSendInput): Promise<{ accepted: boolean; whatsapp_message_id: string }> {
    const record = this.workers.get(input.session_uuid);
    if (!record) throw new Error('session_not_ready');

    return await this.request(record, 'send', input) as { accepted: boolean; whatsapp_message_id: string };
  }

  onCampaignAcknowledgement(listener: (campaignKey: string, messageId: string, ack: number) => void): void {
    this.campaignAcknowledgement = listener;
  }

  async sendCampaign(input: CampaignSendInput): Promise<string> {
    const record = this.workers.get(input.sessionUuid);
    if (!record) throw new Error('session_not_found');

    return await this.request(record, 'sendCampaign', input) as string;
  }

  async destroyAll(): Promise<void> {
    for (const timer of this.restartTimers.values()) clearTimeout(timer);
    this.restartTimers.clear();

    await Promise.allSettled([...this.workers.values()].map(async (record) => {
      record.intentionalShutdown = true;
      await this.stop(record);
    }));

    this.workers.clear();
  }

  private spawn(input: SessionInput): WorkerRecord {
    const sourceMode = import.meta.url.endsWith('.ts');
    const workerFile = fileURLToPath(new URL(sourceMode ? './session-worker.ts' : './session-worker.js', import.meta.url));

    const child = fork(workerFile, [], {
      stdio: ['ignore', 'inherit', 'inherit', 'ipc'],
      execArgv: process.execArgv,
      env: {
        ...process.env,
        CONNECTOR_INSTANCE_ID: `${this.config.instanceId ?? 'connector'}:${input.session_uuid.slice(0, 8)}`,
        SESSION_WORKER_UUID: input.session_uuid,
      },
    });

    const record: WorkerRecord = {
      child,
      input,
      state: 'initializing',
      intentionalShutdown: false,
      pending: new Map(),
    };

    this.workers.set(input.session_uuid, record);

    child.on('message', (message: WorkerMessage) => this.onMessage(record, message));
    child.once('exit', (code, signal) => this.onExit(record, code, signal));
    child.once('error', (error) => {
      console.error('session worker process error', {
        session_uuid: input.session_uuid,
        message: error.message,
      });
    });

    return record;
  }

  private onMessage(record: WorkerRecord, message: WorkerMessage): void {
    if (!message || typeof message !== 'object') return;

    if (message.kind === 'response') {
      const pending = record.pending.get(message.id);
      if (!pending) return;

      clearTimeout(pending.timer);
      record.pending.delete(message.id);

      if (message.ok) pending.resolve(message.result);
      else pending.reject(new Error(message.error ?? 'session_worker_error'));
      return;
    }

    if (message.event === 'status') {
      const status = message.payload.status as { status?: unknown } | null | undefined;
      if (status && typeof status.status === 'string') record.state = status.status;
      return;
    }

    if (message.event === 'campaign_ack') {
      const campaignKey = String(message.payload.campaignKey ?? '');
      const messageId = String(message.payload.messageId ?? '');
      const ack = Number(message.payload.ack ?? 0);
      if (campaignKey && messageId && Number.isFinite(ack)) {
        this.campaignAcknowledgement?.(campaignKey, messageId, ack);
      }
    }
  }

  private onExit(record: WorkerRecord, code: number | null, signal: NodeJS.Signals | null): void {
    const uuid = record.input.session_uuid;

    for (const pending of record.pending.values()) {
      clearTimeout(pending.timer);
      pending.reject(new Error('session_worker_exited'));
    }
    record.pending.clear();

    if (this.workers.get(uuid) === record) this.workers.delete(uuid);
    if (record.intentionalShutdown) return;

    const now = Date.now();
    const previous = (this.crashHistory.get(uuid) ?? [])
      .filter((timestamp) => now - timestamp <= this.config.sessionWorkers.restartWindowMs);
    previous.push(now);
    this.crashHistory.set(uuid, previous);

    console.error('session worker exited unexpectedly', {
      session_uuid: uuid,
      code,
      signal,
      crashes_in_window: previous.length,
    });

    if (previous.length > this.config.sessionWorkers.maxRestarts) {
      this.crashLoops.add(uuid);
      console.error('session worker restart limit reached', { session_uuid: uuid });
      return;
    }

    const delay = Math.min(60_000, this.config.sessionWorkers.restartBaseDelayMs * 2 ** Math.max(0, previous.length - 1));
    const timer = setTimeout(() => {
      this.restartTimers.delete(uuid);
      void this.respawn(record.input);
    }, delay);
    timer.unref();
    this.restartTimers.set(uuid, timer);
  }

  private async respawn(input: SessionInput): Promise<void> {
    if (this.workers.has(input.session_uuid) || this.crashLoops.has(input.session_uuid)) return;

    try {
      const record = this.spawn(input);
      record.state = 'reconnecting';
      await this.request(record, 'initialize', input);
    } catch (error) {
      console.error('session worker recovery failed', {
        session_uuid: input.session_uuid,
        message: error instanceof Error ? error.message : 'unknown',
      });

      const record = this.workers.get(input.session_uuid);
      if (record && !record.intentionalShutdown) record.child.kill('SIGKILL');
    }
  }

  private cancelRestart(uuid: string): void {
    const timer = this.restartTimers.get(uuid);
    if (timer) clearTimeout(timer);
    this.restartTimers.delete(uuid);
    this.crashLoops.delete(uuid);
  }

  private async stop(record: WorkerRecord): Promise<void> {
    if (record.child.exitCode !== null || record.child.killed) return;

    try {
      await this.request(record, 'destroy');
    } catch {
      // The worker may exit before returning the destroy acknowledgement.
    }

    if (record.child.exitCode === null) record.child.kill('SIGTERM');
  }

  private request(record: WorkerRecord, action: WorkerRequest['action'], payload?: unknown): Promise<unknown> {
    if (!record.child.connected) return Promise.reject(new Error('session_worker_unavailable'));

    const id = crypto.randomUUID();

    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        record.pending.delete(id);
        reject(new Error('session_worker_timeout'));
      }, this.config.sessionWorkers.requestTimeoutMs);
      timer.unref();

      record.pending.set(id, { resolve, reject, timer });

      const message: WorkerRequest = { kind: 'request', id, action, payload };
      record.child.send(message, (error) => {
        if (!error) return;
        clearTimeout(timer);
        record.pending.delete(id);
        reject(error);
      });
    });
  }
}
