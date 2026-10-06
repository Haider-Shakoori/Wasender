import express from 'express';
import helmet from 'helmet';
import type { Config } from './config.js';
import { NonceStore, verifySignature } from './security.js';
import type { SessionRuntime } from './session-runtime.js';
import { RequestRegistry } from './request-registry.js';
import type { SendRecord } from './request-registry.js';
import type { CampaignService } from './campaign-service.js';
import { uuidPattern } from './campaign-types.js';

export function createApp(config: Config, sessions: SessionRuntime, campaigns?: CampaignService, isDraining: () => boolean = () => false) {
  const app = express();
  app.disable('x-powered-by');
  app.use(helmet());
  app.use(express.json({ limit: `${Math.max(65_536, config.campaign.maxRequestBytes)}b` }));
  app.get('/health', (_request, response) => response.status(isDraining() ? 503 : 200).json({ status: isDraining() ? 'draining' : 'ok' }));
  app.get('/health/live', (_request, response) => response.json({ status: 'ok' }));
  app.get('/health/ready', (_request, response) => response.status(isDraining() ? 503 : 200).json({ status: isDraining() ? 'draining' : 'ready' }));
  app.use((request, response, next) => {
    if (isDraining() && request.method !== 'GET') { response.status(503).json({ error: 'connector_draining' }); return; }
    next();
  });
  app.use('/internal', verifySignature(config.hmacSecret, new NonceStore(300_000, `${config.campaign.storePath}.nonces`)));
  const requests = new RequestRegistry(config.messageRequests.storePath, config.messageRequests.ttlMs);
  app.get('/internal/health', (_request, response) => response.json({ status: isDraining() ? 'draining' : 'ok', service: 'whatsapp-session-service', ...sessions.health(), campaign_transport: campaigns?.health() ?? { enabled: false } }));
  app.post('/internal/sessions/:uuid/initialize', asyncRoute(async (request, response) => {
    const uuid = String(request.params.uuid); const state = sessions.status(uuid);
    state ? response.json(state) : response.status(404).json({ error: 'session_not_loaded' });
  }));
  app.post('/internal/sessions', asyncRoute(async (request, response) => {
    const input = { session_uuid: request.body.reference, storage_key: request.body.storage_key, tenant_uuid: request.body.tenant_uuid };
    void sessions.initialize(input).catch((error) => console.error('session initialization failed', {
      session_uuid: input.session_uuid,
      message: error instanceof Error ? error.message : 'unknown',
    }));
    response.status(202).json({ reference: input.session_uuid, status: 'initializing' });
  }));
  app.get('/internal/sessions/:uuid', (request, response) => {
    const state = sessions.status(String(request.params.uuid));
    state ? response.json(state) : response.status(404).json({ error: 'session_not_loaded' });
  });
  app.post('/internal/sessions/:uuid/reconnect', asyncRoute(async (request, response) => {
    const uuid = String(request.params.uuid);
    void sessions.restart(uuid, { storage_key: request.body.storage_key, tenant_uuid: request.body.tenant_uuid }).catch((error) => console.error('session reconnect failed', {
      session_uuid: uuid,
      message: error instanceof Error ? error.message : 'unknown',
    }));
    response.status(202).json({ reference: uuid, status: 'reconnecting' });
  }));
  app.post('/internal/sessions/:uuid/logout', asyncRoute(async (request, response) => {
    const uuid = String(request.params.uuid);
    await sessions.logout(uuid); response.status(202).json({ reference: uuid, status: 'disconnected' });
  }));
  app.post('/internal/sessions/:uuid/disconnect', asyncRoute(async (request, response) => {
    const uuid = String(request.params.uuid);
    await sessions.disconnect(uuid); response.status(202).json({ reference: uuid, status: 'disconnected' });
  }));
  app.delete('/internal/sessions/:uuid', asyncRoute(async (request, response) => {
    const uuid = String(request.params.uuid);
    await sessions.remove({ session_uuid: uuid, storage_key: request.body.storage_key });
    response.status(202).json({ reference: uuid, status: 'deleted' });
  }));
  app.post('/internal/messages/send', asyncRoute(async (request, response) => {
    const { record, duplicate } = requests.begin(request.body.request_id, request.body, request.body.message_uuid);
    if (duplicate) {
      respondWithRequestRecord(record, response);
      return;
    }

    try {
      const result = await sessions.send(request.body);
      requests.complete(record.requestId, result);
      response.json(result);
    } catch (error) {
      const code = error instanceof Error ? error.message : 'connector_error';
      if (['session_worker_exited', 'session_worker_timeout', 'session_worker_unavailable'].includes(code)) {
        requests.unknown(record.requestId);
      } else {
        requests.fail(record.requestId, code);
      }
      throw error;
    }
  }));
  app.get('/internal/messages/requests/:id', (request, response) => {
    const record = requests.get(String(request.params.id));
    if (!record) {
      response.status(404).json({ error: 'request_not_found' });
      return;
    }
    respondWithRequestRecord(record, response);
  });
  app.post('/internal/v1/campaign-messages/send', asyncRoute(async (request, response) => {
    if (!campaigns) { response.status(503).json({ error: 'campaign_transport_disabled' }); return; }
    const requestId = request.header('x-internal-request-id') ?? '';
    const idempotencyKey = request.header('x-internal-idempotency-key') ?? '';
    if (!uuidPattern.test(requestId) || !idempotencyKey) { response.status(422).json({ error: 'invalid_request_schema' }); return; }
    const result = await campaigns.dispatch(request.body, requestId, idempotencyKey);
    response.status(result.status === 'failed' ? 422 : 200).json(result);
  }));
  app.get('/internal/v1/campaign-dispatches/:uuid', (request, response) => {
    if (!campaigns || !uuidPattern.test(String(request.params.uuid))) { response.status(404).json({ error: 'dispatch_not_found' }); return; }
    const tenant = request.header('x-campaign-tenant-uuid') ?? '';
    const idempotency = request.header('x-internal-idempotency-key') ?? '';
    const result = campaigns.lookup(String(request.params.uuid), tenant, idempotency);
    result ? response.json(result) : response.status(404).json({ status: 'not_found' });
  });
  app.use((error: unknown, _request: express.Request, response: express.Response, _next: express.NextFunction) => {
    console.error('connector request failed', { message: error instanceof Error ? error.message : 'unknown' });
    response.status(422).json({ error: error instanceof Error ? error.message : 'connector_error' });
  });
  return app;
}

function asyncRoute(handler: (request: express.Request, response: express.Response) => Promise<void>) {
  return (request: express.Request, response: express.Response, next: express.NextFunction) => void handler(request, response).catch(next);
}

function respondWithRequestRecord(record: SendRecord, response: express.Response): void {
  if (record.status === 'sent' && record.result) {
    response.json(record.result);
    return;
  }

  if (record.status === 'unknown') {
    response.status(409).json({ error: 'request_outcome_unknown' });
    return;
  }

  if (record.status === 'failed') {
    response.status(422).json({ error: record.error ?? 'connector_rejected' });
    return;
  }

  response.status(409).json({ error: 'request_in_progress' });
}
