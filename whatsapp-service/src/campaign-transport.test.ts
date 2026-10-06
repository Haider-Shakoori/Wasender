import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import { CampaignStore } from './campaign-store.js';
import { validateCampaignDispatch } from './campaign-types.js';
import { CampaignService } from './campaign-service.js';
import { SessionManager } from './session-manager.js';
import { CallbackClient } from './callback-client.js';
import type { Config } from './config.js';

const uuid = {
  tenant: '10000000-0000-4000-8000-000000000001', campaign: '10000000-0000-4000-8000-000000000002',
  execution: '10000000-0000-4000-8000-000000000003', recipient: '10000000-0000-4000-8000-000000000004',
  attempt: '10000000-0000-4000-8000-000000000005', session: '10000000-0000-4000-8000-000000000006',
};
const payload = {
  version: 1, tenant_uuid: uuid.tenant, campaign_uuid: uuid.campaign, execution_uuid: uuid.execution,
  recipient_execution_uuid: uuid.recipient, dispatch_attempt_uuid: uuid.attempt, session_uuid: uuid.session,
  recipient: { phone_normalized: '+15551234567', whatsapp_address: '15551234567@c.us' },
  message: { type: 'text', body: 'hello', caption: null, attachment: null }, attempt_number: 1,
  campaign_payload_hash: 'a'.repeat(64), transport_request_hash: 'b'.repeat(64), idempotency_key: 'c'.repeat(64),
  requested_at: new Date().toISOString(), metadata: { source: 'campaign' },
};

function runtimeConfig(directory: string): Pick<Config, 'host' | 'callbackOutboxRoot' | 'messageRequests' | 'sessionWorkers'> {
  return {
    host: '127.0.0.1',
    callbackOutboxRoot: path.join(directory, 'callback-outbox'),
    messageRequests: {
      storePath: path.join(directory, 'message-requests.json'),
      ttlMs: 60_000,
    },
    sessionWorkers: {
      enabled: false,
      requestTimeoutMs: 5_000,
      maxRestarts: 2,
      restartWindowMs: 60_000,
      restartBaseDelayMs: 100,
      maxActive: 10,
      diskCriticalPercent: 95,
    },
  };
}

describe('campaign transport safety', () => {
  it('accepts only strict direct-contact dispatches', () => {
    expect(validateCampaignDispatch(payload).recipient.whatsapp_address).toBe('15551234567@c.us');
    expect(() => validateCampaignDispatch({ ...payload, recipient: { phone_normalized: '+15551234567', whatsapp_address: '15551234567@g.us' } })).toThrow('unsupported_recipient_address');
    expect(() => validateCampaignDispatch({ ...payload, arbitrary_option: true })).toThrow('invalid_request_schema');
  });

  it('persists idempotency and marks interrupted sends unknown after restart', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'campaign-store-'));
    const filename = path.join(directory, 'store.json');
    const result = { success: false, request_id: uuid.tenant, idempotency_key: payload.idempotency_key, dispatch_attempt_uuid: uuid.attempt, status: 'sending' as const, transport_reference: null, whatsapp_message_id: null, duplicate: false, failure: null, server_time: new Date().toISOString() };
    const first = new CampaignStore(filename, 60_000);
    first.put({ requestHash: 'd'.repeat(64), tenantUuid: uuid.tenant, sessionUuid: uuid.session, dispatchAttemptUuid: uuid.attempt, idempotencyKey: payload.idempotency_key, status: 'sending', result, updatedAt: Date.now() });
    expect(new CampaignStore(filename, 60_000).getByKey(payload.idempotency_key)?.status).toBe('unknown');
    fs.rmSync(directory, { recursive: true, force: true });
  });

  it('returns the prior authoritative result without sending twice', async () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'campaign-service-'));
    const config: Config = {
      ...runtimeConfig(directory),
      port: 3100, hmacSecret: 's'.repeat(32), callbackUrl: 'http://127.0.0.1:1/internal/whatsapp/events',
      authRoot: path.join(directory, 'auth'), callbackTimeoutMs: 20, maxReconnectAttempts: 1,
      campaign: {
        enabled: true, storePath: path.join(directory, 'campaign.json'), laravelBaseUrl: 'http://127.0.0.1:1',
        maxRequestBytes: 65_536, idempotencyTtlMs: 60_000, maxMediaBytes: 1024, mediaTimeoutMs: 20,
        sendTimeoutMs: 100, sessionConcurrency: 1, callbackMaxAttempts: 0, callbackBaseDelayMs: 10,
        callbackMaxDelayMs: 20, correlationRetentionMs: 60_000,
      },
    };
    const callbacks = new CallbackClient(config.callbackUrl, config.hmacSecret, config.callbackTimeoutMs);
    vi.spyOn(callbacks, 'emitCampaign').mockResolvedValue();
    const sessions = new SessionManager(config, callbacks);
    const sender = vi.spyOn(sessions, 'sendCampaign').mockResolvedValue('stable-message-id');
    const service = new CampaignService(config, sessions, callbacks);
    const first = await service.dispatch(payload, uuid.tenant, payload.idempotency_key);
    const duplicate = await service.dispatch(payload, uuid.campaign, payload.idempotency_key);
    expect(first.status).toBe('sent');
    expect(duplicate.duplicate).toBe(true);
    expect(duplicate.whatsapp_message_id).toBe('stable-message-id');
    expect(sender).toHaveBeenCalledTimes(1);
    await expect(service.dispatch({ ...payload, transport_request_hash: 'e'.repeat(64) }, uuid.execution, payload.idempotency_key)).rejects.toThrow('idempotency_conflict');
    expect(sender).toHaveBeenCalledTimes(1);
    service.stop();
    fs.rmSync(directory, { recursive: true, force: true });
  });

  it('rejects a cross-tenant session before invoking the WhatsApp client', async () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'campaign-session-'));
    const config: Config = {
      ...runtimeConfig(directory),
      port: 3100, hmacSecret: 's'.repeat(32), callbackUrl: 'http://127.0.0.1:1/internal/whatsapp/events',
      authRoot: path.join(directory, 'auth'), callbackTimeoutMs: 20, maxReconnectAttempts: 1,
      campaign: { enabled: true, storePath: path.join(directory, 'store.json'), laravelBaseUrl: 'http://127.0.0.1:1', maxRequestBytes: 65_536, idempotencyTtlMs: 60_000, maxMediaBytes: 1024, mediaTimeoutMs: 20, sendTimeoutMs: 100, sessionConcurrency: 1, callbackMaxAttempts: 1, callbackBaseDelayMs: 10, callbackMaxDelayMs: 20, correlationRetentionMs: 60_000 },
    };
    const sessions = new SessionManager(config, new CallbackClient(config.callbackUrl, config.hmacSecret, 20));
    (sessions as unknown as { runtimes: Map<string, unknown> }).runtimes.set(uuid.session, { state: 'ready', tenantUuid: uuid.campaign, inFlight: 0, client: { sendMessage: vi.fn() } });
    await expect(sessions.sendCampaign({ tenantUuid: uuid.tenant, sessionUuid: uuid.session, recipientAddress: '15551234567@c.us', type: 'text', body: 'hello', attachment: null, campaignKey: payload.idempotency_key })).rejects.toThrow('session_tenant_mismatch');
    fs.rmSync(directory, { recursive: true, force: true });
  });

  it('blocks checksum-mismatched media before WhatsApp send', async () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'campaign-media-'));
    const config: Config = {
      ...runtimeConfig(directory),
      port: 3100, hmacSecret: 's'.repeat(32), callbackUrl: 'http://app.test/internal/whatsapp/events',
      authRoot: path.join(directory, 'auth'), callbackTimeoutMs: 20, maxReconnectAttempts: 1,
      campaign: { enabled: true, storePath: path.join(directory, 'store.json'), laravelBaseUrl: 'http://app.test', maxRequestBytes: 65_536, idempotencyTtlMs: 60_000, maxMediaBytes: 1024, mediaTimeoutMs: 20, sendTimeoutMs: 100, sessionConcurrency: 1, callbackMaxAttempts: 1, callbackBaseDelayMs: 10, callbackMaxDelayMs: 20, correlationRetentionMs: 60_000 },
    };
    const sessions = new SessionManager(config, new CallbackClient(config.callbackUrl, config.hmacSecret, 20));
    const sendMessage = vi.fn();
    (sessions as unknown as { runtimes: Map<string, unknown> }).runtimes.set(uuid.session, { state: 'ready', tenantUuid: uuid.tenant, inFlight: 0, client: { sendMessage } });
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(new Uint8Array([1, 2, 3]), { status: 200, headers: { 'content-type': 'image/jpeg', 'content-length': '3' } })));
    await expect(sessions.sendCampaign({
      tenantUuid: uuid.tenant, sessionUuid: uuid.session, recipientAddress: '15551234567@c.us', type: 'image', body: null, campaignKey: payload.idempotency_key,
      attachment: { retrieval_url: `http://app.test/internal/whatsapp/campaign-attachments/${uuid.campaign}`, retrieval_token: 't'.repeat(64), mime_type: 'image/jpeg', original_name: 'photo.jpg', size_bytes: 3, checksum_sha256: '0'.repeat(64) },
    })).rejects.toThrow('attachment_checksum_mismatch');
    expect(sendMessage).not.toHaveBeenCalled();
    vi.unstubAllGlobals();
    fs.rmSync(directory, { recursive: true, force: true });
  });
});
