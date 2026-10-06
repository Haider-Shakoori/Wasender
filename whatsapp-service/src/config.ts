import path from 'node:path';

function required(name: string): string {
  const value = process.env[name]?.trim();
  if (!value) throw new Error(`${name} is required`);
  return value;
}

export type Config = {
  port: number;
  instanceId?: string;
  sessionLeaseTtlMs?: number;
  hmacSecret: string;
  callbackUrl: string;
  authRoot: string;
  chromiumPath?: string;
  callbackTimeoutMs: number;
  callbackOutboxRoot: string;
  maxReconnectAttempts: number;
  messageRequests: {
    storePath: string;
    ttlMs: number;
  };
  sessionWorkers: {
    enabled: boolean;
    requestTimeoutMs: number;
    maxRestarts: number;
    restartWindowMs: number;
    restartBaseDelayMs: number;
  };
  campaign: {
    enabled: boolean;
    storePath: string;
    laravelBaseUrl: string;
    maxRequestBytes: number;
    idempotencyTtlMs: number;
    maxMediaBytes: number;
    mediaTimeoutMs: number;
    sendTimeoutMs: number;
    sessionConcurrency: number;
    callbackMaxAttempts: number;
    callbackBaseDelayMs: number;
    callbackMaxDelayMs: number;
    correlationRetentionMs: number;
  };
};

export function loadConfig(): Config {
  const port = Number(process.env.PORT ?? 3100);
  const callbackTimeoutMs = Number(process.env.CALLBACK_TIMEOUT_MS ?? 10000);
  const maxReconnectAttempts = Number(process.env.MAX_RECONNECT_ATTEMPTS ?? 5);
  const sessionLeaseTtlMs = Number(process.env.SESSION_LEASE_TTL_SECONDS ?? 180) * 1000;
  const messageRequestTtlMs = Number(process.env.MESSAGE_REQUEST_TTL_HOURS ?? 168) * 3_600_000;
  const workerRequestTimeoutMs = Number(process.env.SESSION_WORKER_REQUEST_TIMEOUT_MS ?? 90000);
  const workerMaxRestarts = Number(process.env.SESSION_WORKER_MAX_RESTARTS ?? 5);
  const workerRestartWindowMs = Number(process.env.SESSION_WORKER_RESTART_WINDOW_SECONDS ?? 600) * 1000;
  const workerRestartBaseDelayMs = Number(process.env.SESSION_WORKER_RESTART_BASE_DELAY_MS ?? 2000);
  if (![port, callbackTimeoutMs, maxReconnectAttempts, sessionLeaseTtlMs, messageRequestTtlMs, workerRequestTimeoutMs, workerMaxRestarts, workerRestartWindowMs, workerRestartBaseDelayMs].every(Number.isFinite)) {
    throw new Error('Numeric connector configuration is invalid');
  }

  return {
    port,
    instanceId: process.env.CONNECTOR_INSTANCE_ID?.trim() || process.env.HOSTNAME?.trim() || `connector-${process.pid}`,
    sessionLeaseTtlMs,
    hmacSecret: required('WHATSAPP_HMAC_SECRET'),
    callbackUrl: required('LARAVEL_CALLBACK_URL'),
    authRoot: path.resolve(process.env.WHATSAPP_AUTH_ROOT ?? './storage/auth'),
    chromiumPath: process.env.PUPPETEER_EXECUTABLE_PATH,
    callbackTimeoutMs,
    callbackOutboxRoot: path.resolve(process.env.CALLBACK_OUTBOX_ROOT ?? './storage/callback-outbox'),
    maxReconnectAttempts,
    messageRequests: {
      storePath: path.resolve(process.env.MESSAGE_REQUEST_STORE_PATH ?? './storage/message-requests.json'),
      ttlMs: messageRequestTtlMs,
    },
    sessionWorkers: {
      enabled: (process.env.SESSION_WORKERS_ENABLED ?? 'true') === 'true',
      requestTimeoutMs: workerRequestTimeoutMs,
      maxRestarts: workerMaxRestarts,
      restartWindowMs: workerRestartWindowMs,
      restartBaseDelayMs: workerRestartBaseDelayMs,
    },
    campaign: {
      enabled: (process.env.CAMPAIGN_TRANSPORT_ENABLED ?? 'true') === 'true',
      storePath: path.resolve(process.env.CAMPAIGN_TRANSPORT_STORE_PATH ?? './storage/campaign-transport.json'),
      laravelBaseUrl: new URL(process.env.LARAVEL_INTERNAL_BASE_URL ?? new URL(required('LARAVEL_CALLBACK_URL')).origin).origin,
      maxRequestBytes: Number(process.env.CAMPAIGN_TRANSPORT_MAX_REQUEST_BYTES ?? 65_536),
      idempotencyTtlMs: Number(process.env.CAMPAIGN_TRANSPORT_IDEMPOTENCY_TTL_HOURS ?? 168) * 3_600_000,
      maxMediaBytes: Number(process.env.CAMPAIGN_TRANSPORT_MAX_MEDIA_MB ?? 25) * 1_048_576,
      mediaTimeoutMs: Number(process.env.CAMPAIGN_TRANSPORT_MEDIA_TIMEOUT_SECONDS ?? 30) * 1000,
      sendTimeoutMs: Number(process.env.CAMPAIGN_TRANSPORT_SEND_TIMEOUT_SECONDS ?? 60) * 1000,
      sessionConcurrency: Number(process.env.CAMPAIGN_TRANSPORT_SESSION_CONCURRENCY ?? 1),
      callbackMaxAttempts: Number(process.env.CAMPAIGN_CALLBACK_MAX_ATTEMPTS ?? 10),
      callbackBaseDelayMs: Number(process.env.CAMPAIGN_CALLBACK_BASE_DELAY_SECONDS ?? 10) * 1000,
      callbackMaxDelayMs: Number(process.env.CAMPAIGN_CALLBACK_MAX_DELAY_SECONDS ?? 900) * 1000,
      correlationRetentionMs: Number(process.env.CAMPAIGN_CORRELATION_RETENTION_DAYS ?? 30) * 86_400_000,
    },
  };
}
