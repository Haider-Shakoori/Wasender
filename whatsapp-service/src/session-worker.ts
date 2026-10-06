import path from 'node:path';
import { CallbackClient } from './callback-client.js';
import { loadConfig } from './config.js';
import { SessionManager } from './session-manager.js';
import { isTransientBrowserNavigationRejection } from './session-worker-errors.js';
import type { CampaignSendInput, DirectSendInput, SessionInput } from './session-runtime.js';
import type { WorkerMessage, WorkerRequest, WorkerResponse } from './session-worker-protocol.js';

const config = loadConfig();
const callbacks = new CallbackClient(config.callbackUrl, config.hmacSecret, config.callbackTimeoutMs, path.join(config.callbackOutboxRoot, `${process.env.SESSION_WORKER_UUID ?? 'worker'}.json`));
const runtime = new SessionManager(config, callbacks);
let current: SessionInput | undefined;
let shuttingDown = false;

function send(message: WorkerMessage): void {
  if (process.connected) process.send?.(message);
}

runtime.onCampaignAcknowledgement((campaignKey, messageId, ack) => {
  send({ kind: 'event', event: 'campaign_ack', payload: { campaignKey, messageId, ack } });
});

const statusTimer = setInterval(() => {
  if (!current) return;
  send({
    kind: 'event',
    event: 'status',
    payload: {
      session_uuid: current.session_uuid,
      status: runtime.status(current.session_uuid),
    },
  });
}, 2_000);
statusTimer.unref();

process.on('message', (message: WorkerRequest) => {
  void handle(message);
});

async function handle(message: WorkerRequest): Promise<void> {
  if (!message || message.kind !== 'request' || !message.id) return;

  try {
    let result: unknown = null;

    switch (message.action) {
      case 'initialize': {
        const input = message.payload as SessionInput;
        current = input;
        await runtime.initialize(input);
        result = runtime.status(input.session_uuid);
        break;
      }
      case 'restart': {
        const payload = message.payload as { uuid: string; fallback?: Pick<SessionInput, 'storage_key' | 'tenant_uuid'> };
        await runtime.restart(payload.uuid, payload.fallback);
        result = runtime.status(payload.uuid);
        break;
      }
      case 'disconnect': {
        const uuid = String(message.payload ?? '');
        await runtime.disconnect(uuid);
        result = true;
        break;
      }
      case 'logout': {
        const uuid = String(message.payload ?? '');
        await runtime.logout(uuid);
        result = true;
        break;
      }
      case 'remove': {
        await runtime.remove(message.payload as SessionInput);
        result = true;
        break;
      }
      case 'heartbeat':
        await runtime.heartbeat();
        result = runtime.health();
        break;
      case 'send':
        result = await runtime.send(message.payload as DirectSendInput);
        break;
      case 'sendCampaign':
        result = await runtime.sendCampaign(message.payload as CampaignSendInput);
        break;
      case 'destroy':
        await shutdown();
        result = true;
        break;
      default:
        throw new Error('unsupported_worker_action');
    }

    const response: WorkerResponse = { kind: 'response', id: message.id, ok: true, result };
    send(response);

    if (message.action === 'destroy') setTimeout(() => process.exit(0), 10).unref();
  } catch (error) {
    const response: WorkerResponse = {
      kind: 'response',
      id: message.id,
      ok: false,
      error: error instanceof Error ? error.message : 'session_worker_error',
    };
    send(response);
  }
}

async function shutdown(): Promise<void> {
  if (shuttingDown) return;
  shuttingDown = true;
  clearInterval(statusTimer);
  await runtime.destroyAll();
}

process.once('SIGTERM', () => void shutdown().finally(() => process.exit(0)));
process.once('SIGINT', () => void shutdown().finally(() => process.exit(0)));

process.on('uncaughtException', (error) => {
  console.error('session worker uncaught exception', { message: error.message });
  process.exit(1);
});

process.on('unhandledRejection', (reason) => {
  const message = reason instanceof Error ? reason.message : String(reason);
  if (isTransientBrowserNavigationRejection(reason)) {
    console.warn('session worker ignored transient browser navigation rejection', { message });
    return;
  }

  console.error('session worker unhandled rejection', { message });
  process.exit(1);
});
