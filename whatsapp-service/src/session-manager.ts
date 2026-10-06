import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import os from 'node:os';
import QRCode from 'qrcode';
import pkg from 'whatsapp-web.js';
import type { Message } from 'whatsapp-web.js';
import type { CallbackClient } from './callback-client.js';
import type { Config } from './config.js';
import { signature } from './security.js';
import { canReclaimSessionLease, type SessionLease } from './session-lease.js';
import type { CampaignSendInput, DirectSendInput, SessionInput, SessionRuntime } from './session-runtime.js';

const { Client, LocalAuth, MessageMedia } = pkg;
type Runtime = { client: InstanceType<typeof Client>; state: string; reconnects: number; storageKey: string; tenantUuid?: string; inFlight: number; leasePath: string };

export class SessionManager implements SessionRuntime {
  private readonly runtimes = new Map<string, Runtime>();
  private readonly sent = new Map<string, { messageUuid?: string; requestId?: string; campaignKey?: string; ack: number }>();
  private readonly instanceId: string;
  private readonly leaseTtlMs: number;
  private campaignAcknowledgement?: (campaignKey: string, messageId: string, ack: number) => void;
  constructor(private readonly config: Config, private readonly callbacks: CallbackClient) {
    this.instanceId = config.instanceId ?? process.env.HOSTNAME ?? `connector-${process.pid}`;
    this.leaseTtlMs = config.sessionLeaseTtlMs ?? 180_000;
    fs.mkdirSync(config.authRoot, { recursive: true, mode: 0o700 });
    try { fs.chmodSync(config.authRoot, 0o700); } catch { /* filesystem may not support chmod */ }
  }

  private validate(input: SessionInput): void {
    if (!/^[0-9a-f-]{36}$/i.test(input.session_uuid) || !/^[a-zA-Z0-9_-]{24,96}$/.test(input.storage_key)) {
      throw new Error('invalid_session_identity');
    }
    const target = path.resolve(this.config.authRoot, `session-${input.storage_key}`);
    if (!target.startsWith(`${this.config.authRoot}${path.sep}`)) throw new Error('invalid_storage_path');
  }

  async initialize(input: SessionInput): Promise<void> {
    this.validate(input);
    if (this.runtimes.has(input.session_uuid)) return;
    const leasePath = this.acquireLease(input.session_uuid);
    this.clearStaleChromiumProfileLocks(input.storage_key);
    const client = new Client({
      authTimeoutMs: this.config.whatsappAuthTimeoutMs ?? 180_000,
      authStrategy: new LocalAuth({ clientId: input.storage_key, dataPath: this.config.authRoot }),
      puppeteer: {
        headless: true,
        executablePath: this.config.chromiumPath,
        protocolTimeout: this.config.chromiumProtocolTimeoutMs ?? 300_000,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
      },
    });
    if (input.tenant_uuid && !/^[0-9a-f-]{36}$/i.test(input.tenant_uuid)) throw new Error('invalid_tenant_identity');
    const runtime: Runtime = { client, state: 'initializing', reconnects: 0, storageKey: input.storage_key, tenantUuid: input.tenant_uuid, inFlight: 0, leasePath };
    this.runtimes.set(input.session_uuid, runtime);
    client.on('qr', async (qr) => {
      runtime.state = 'qr_pending';
      const png = await QRCode.toDataURL(qr, { errorCorrectionLevel: 'M', margin: 2, width: 320 });
      await this.safeEmit(input.session_uuid, 'qr', { qr: png });
    });
    client.on('authenticated', () => { runtime.state = 'authenticated'; void this.safeEmit(input.session_uuid, 'authenticated'); });
    client.on('ready', () => {
      runtime.state = 'ready'; runtime.reconnects = 0;
      const info = client.info;
      void this.safeEmit(input.session_uuid, 'ready', {
        phone_number: info?.wid?.user,
        display_name: info?.pushname,
        platform: info?.platform,
        wid: info?.wid?._serialized,
      });
    });
    client.on('auth_failure', (message) => {
      runtime.state = 'failed';
      void this.safeEmit(input.session_uuid, 'auth_failure', { code: 'auth_failure', message: String(message).slice(0, 500) });
    });
    client.on('disconnected', (reason) => {
      runtime.state = 'disconnected';
      void this.safeEmit(input.session_uuid, 'disconnected', { reason: String(reason).slice(0, 255) });
    });
    client.on('message_ack', (message, ack) => {
      const id = message.id?._serialized; const mapped = id ? this.sent.get(id) : undefined;
      if (!mapped || ack <= mapped.ack) return;
      mapped.ack = ack;
      if (mapped.campaignKey) { this.campaignAcknowledgement?.(mapped.campaignKey, id!, ack); return; }
      const event = ack >= 3 ? 'read' : ack >= 2 ? 'delivered' : null;
      if (event && mapped.messageUuid && mapped.requestId) void this.callbacks.emitMessage(mapped.messageUuid, mapped.requestId, event, { whatsapp_message_id: id });
    });
    client.on('message', (message) => {
      void this.forwardInboundMessage(input, message).catch((error) => console.error('inbox callback delivery failed', {
        session_uuid: input.session_uuid, message: error instanceof Error ? error.message : 'unknown',
      }));
    });
    try { await client.initialize(); }
    catch (error) {
      runtime.state = 'failed';
      await this.safeEmit(input.session_uuid, 'auth_failure', {
        code: 'initialization_failed',
        message: error instanceof Error ? error.message.slice(0, 500) : 'WhatsApp initialization failed.',
      });
      try { await client.destroy(); } catch { /* best-effort cleanup after failed initialization */ }
      this.runtimes.delete(input.session_uuid);
      this.releaseLease(runtime);
      throw error;
    }
  }

  status(uuid: string): Record<string, unknown> | null {
    const runtime = this.runtimes.get(uuid);
    return runtime ? { reference: uuid, status: runtime.state, metadata: { reconnect_attempts: runtime.reconnects } } : null;
  }

  async restart(uuid: string, fallback?: Pick<SessionInput, 'storage_key' | 'tenant_uuid'>): Promise<void> {
    const existing = this.runtimes.get(uuid);
    const input: SessionInput = { session_uuid: uuid, storage_key: existing?.storageKey ?? fallback?.storage_key ?? '', tenant_uuid: existing?.tenantUuid ?? fallback?.tenant_uuid };
    this.validate(input);
    if (existing) {
      if (existing.reconnects >= this.config.maxReconnectAttempts) throw new Error('reconnect_limit_reached');
      existing.reconnects += 1;
      await existing.client.destroy();
      this.runtimes.delete(uuid);
      this.releaseLease(existing);
    }
    await this.initialize(input);
  }

  async disconnect(uuid: string): Promise<void> {
    const runtime = this.runtimes.get(uuid);
    if (!runtime) return;
    runtime.state = 'disconnected';
    await runtime.client.destroy();
    this.runtimes.delete(uuid);
    this.releaseLease(runtime);
  }

  async logout(uuid: string): Promise<void> {
    const runtime = this.runtimes.get(uuid);
    if (!runtime) return;
    await runtime.client.logout();
    await runtime.client.destroy();
    this.runtimes.delete(uuid);
    this.releaseLease(runtime);
  }

  async remove(input: SessionInput): Promise<void> {
    this.validate(input);
    const uuid = input.session_uuid;
    const runtime = this.runtimes.get(uuid);
    if (runtime) {
      input.storage_key = runtime.storageKey;
      await this.logout(uuid);
    }
    const target = path.resolve(this.config.authRoot, `session-${input.storage_key}`);
    fs.rmSync(target, { recursive: true, force: true });
  }

  async heartbeat(): Promise<void> {
    await Promise.all([...this.runtimes.entries()].map(async ([uuid, runtime]) => {
      if (!this.renewLease(runtime)) { this.runtimes.delete(uuid); return; }
      await this.safeEmit(uuid, 'heartbeat', { state: runtime.state });
    }));
  }

  health(): Record<string, unknown> {
    return { instance_id: this.instanceId, owned_sessions: this.runtimes.size, ready_sessions: [...this.runtimes.values()].filter((runtime) => runtime.state === 'ready').length };
  }

  async send(input: DirectSendInput): Promise<{ accepted: boolean; whatsapp_message_id: string }> {
    if (!/^[0-9a-f-]{36}$/i.test(input.message_uuid) || !/^[0-9a-f-]{36}$/i.test(input.request_id) || !/^[1-9][0-9]{7,14}$/.test(input.recipient)) throw new Error('invalid_message_request');
    if (input.expires_at && Date.parse(input.expires_at) <= Date.now()) throw new Error('message_expired');
    if (!['text', 'image', 'document', 'audio', 'video'].includes(input.type)) throw new Error('unsupported_message_type');
    const runtime = this.runtimes.get(input.session_uuid);
    if (!runtime || runtime.state !== 'ready') throw new Error('session_not_ready');
    let content: string | InstanceType<typeof MessageMedia> = input.body ?? '';
    const options: Record<string, unknown> = {};
    if (input.type !== 'text') {
      if (!input.media) throw new Error('media_required');
      const media = await this.fetchMedia(input.media);
      content = new MessageMedia(input.media.mime_type, media.toString('base64'), input.media.filename);
      if (input.body) options.caption = input.body;
      if (input.type === 'document') options.sendMediaAsDocument = true;
    } else if (!input.body?.trim()) throw new Error('body_required');
    const result = await runtime.client.sendMessage(`${input.recipient}@c.us`, content, options);
    const externalId = result.id._serialized;
    this.sent.set(externalId, { messageUuid: input.message_uuid, requestId: input.request_id, ack: 1 });
    return { accepted: true, whatsapp_message_id: externalId };
  }

  onCampaignAcknowledgement(listener: (campaignKey: string, messageId: string, ack: number) => void): void {
    this.campaignAcknowledgement = listener;
  }

  async sendCampaign(input: CampaignSendInput): Promise<string> {
    const runtime = this.runtimes.get(input.sessionUuid);
    if (!runtime) throw new Error('session_not_found');
    if (!runtime.tenantUuid || runtime.tenantUuid !== input.tenantUuid) throw new Error('session_tenant_mismatch');
    if (runtime.state !== 'ready') throw new Error(runtime.state === 'disconnected' ? 'session_disconnected' : 'session_reconnecting');
    if (runtime.inFlight >= this.config.campaign.sessionConcurrency) throw new Error('session_capacity_exceeded');
    if (!/^[1-9][0-9]{7,14}@c\.us$/.test(input.recipientAddress)) throw new Error('unsupported_recipient_address');
    runtime.inFlight++;
    try {
      let content: string | InstanceType<typeof MessageMedia> = input.body ?? '';
      const options: Record<string, unknown> = {};
      if (input.type !== 'text') {
        if (!input.attachment) throw new Error('attachment_missing');
        const media = await this.fetchCampaignMedia(input.attachment);
        content = new MessageMedia(input.attachment.mime_type, media.toString('base64'), path.basename(input.attachment.original_name));
        if (input.body) options.caption = input.body;
        if (input.type === 'document') options.sendMediaAsDocument = true;
      }
      const result = await runtime.client.sendMessage(input.recipientAddress, content, options);
      const messageId = result.id?._serialized;
      if (!messageId || messageId.length > 512) throw new Error('whatsapp_message_id_missing');
      this.sent.set(messageId, { campaignKey: input.campaignKey, ack: 1 });
      return messageId;
    } finally { runtime.inFlight--; }
  }

  private async fetchCampaignMedia(media: { retrieval_url: string; retrieval_token: string; mime_type: string; original_name: string; size_bytes: number; checksum_sha256: string }): Promise<Buffer> {
    const url = new URL(media.retrieval_url);
    if (url.origin !== this.config.campaign.laravelBaseUrl || !url.pathname.startsWith('/internal/whatsapp/campaign-attachments/')) throw new Error('attachment_token_invalid');
    if (media.size_bytes < 1 || media.size_bytes > this.config.campaign.maxMediaBytes) throw new Error('attachment_too_large');
    const timestamp = Math.floor(Date.now() / 1000).toString(); const nonce = crypto.randomUUID();
    const requestId = crypto.randomUUID();
    const headers = {
      authorization: `Bearer ${media.retrieval_token}`,
      'x-internal-source': 'whatsapp-connector', 'x-internal-timestamp': timestamp, 'x-internal-nonce': nonce, 'x-internal-request-id': requestId,
      'x-internal-idempotency-key': requestId, 'x-internal-content-sha256': signatureHash(''),
      'x-internal-signature': signature(this.config.hmacSecret, 'GET', url.pathname, timestamp, nonce, '', requestId, requestId),
    };
    const response = await fetch(url, { headers, redirect: 'error', signal: AbortSignal.timeout(this.config.campaign.mediaTimeoutMs) });
    const contentType = response.headers.get('content-type')?.split(';')[0];
    if (!response.ok) throw new Error('attachment_download_failed');
    if (contentType !== media.mime_type || !allowedCampaignMime(media.mime_type)) throw new Error('attachment_mime_mismatch');
    const declaredLength = Number(response.headers.get('content-length') ?? media.size_bytes);
    if (declaredLength !== media.size_bytes || declaredLength > this.config.campaign.maxMediaBytes) throw new Error('attachment_too_large');
    const buffer = Buffer.from(await response.arrayBuffer());
    if (buffer.length !== media.size_bytes) throw new Error('attachment_download_failed');
    const actual = crypto.createHash('sha256').update(buffer).digest('hex');
    if (actual.length !== media.checksum_sha256.length || !crypto.timingSafeEqual(Buffer.from(actual), Buffer.from(media.checksum_sha256))) throw new Error('attachment_checksum_mismatch');
    return buffer;
  }

  private async fetchMedia(media: { url: string; mime_type: string; filename: string; size: number; checksum_sha256: string }): Promise<Buffer> {
    const url = new URL(media.url); const allowed = new URL(this.config.callbackUrl);
    if (url.origin !== allowed.origin || !url.pathname.startsWith('/internal/whatsapp/messages/')) throw new Error('invalid_media_origin');
    if (media.size < 1 || media.size > 16 * 1024 * 1024) throw new Error('invalid_media_size');
    const timestamp = Math.floor(Date.now() / 1000).toString(); const nonce = crypto.randomUUID();
    const headers = { 'x-internal-source': 'whatsapp-connector', 'x-internal-timestamp': timestamp, 'x-internal-nonce': nonce,
      'x-internal-signature': signature(this.config.hmacSecret, 'GET', url.pathname, timestamp, nonce, '') };
    const response = await fetch(url, { headers, redirect: 'error', signal: AbortSignal.timeout(this.config.callbackTimeoutMs) });
    if (!response.ok || response.headers.get('content-type')?.split(';')[0] !== media.mime_type) throw new Error('media_fetch_failed');
    const buffer = Buffer.from(await response.arrayBuffer());
    if (buffer.length !== media.size || crypto.createHash('sha256').update(buffer).digest('hex') !== media.checksum_sha256) throw new Error('media_integrity_failed');
    return buffer;
  }

  async destroyAll(): Promise<void> {
    const runtimes = [...this.runtimes.values()];
    await Promise.allSettled(runtimes.map((runtime) => runtime.client.destroy()));
    runtimes.forEach((runtime) => this.releaseLease(runtime));
    this.runtimes.clear();
  }

  private acquireLease(uuid: string): string {
    const directory = path.join(this.config.authRoot, '.leases');
    fs.mkdirSync(directory, { recursive: true });
    const leasePath = path.join(directory, `${uuid}.json`);
    for (let attempt = 0; attempt < 2; attempt++) {
      try {
        fs.writeFileSync(leasePath, JSON.stringify({ owner: this.instanceId, host: os.hostname(), pid: process.pid, expires_at: Date.now() + this.leaseTtlMs }), { flag: 'wx', mode: 0o600 });
        return leasePath;
      } catch (error) {
        if ((error as NodeJS.ErrnoException).code !== 'EEXIST') throw error;
        try {
          const existing = JSON.parse(fs.readFileSync(leasePath, 'utf8')) as SessionLease;
          if (!canReclaimSessionLease(existing, this.instanceId, os.hostname(), (pid) => this.processAlive(pid))) {
            throw new Error('session_owned_by_healthy_connector');
          }
          fs.unlinkSync(leasePath);
        } catch (readError) {
          if (readError instanceof Error && readError.message === 'session_owned_by_healthy_connector') throw readError;
          try { fs.unlinkSync(leasePath); } catch { /* acquisition retry decides ownership */ }
        }
      }
    }
    throw new Error('session_lease_unavailable');
  }

  private renewLease(runtime: Runtime): boolean {
    try {
      const lease = JSON.parse(fs.readFileSync(runtime.leasePath, 'utf8')) as { owner?: string };
      if (lease.owner !== this.instanceId) throw new Error('session_lease_lost');
      fs.writeFileSync(runtime.leasePath, JSON.stringify({ owner: this.instanceId, host: os.hostname(), pid: process.pid, expires_at: Date.now() + this.leaseTtlMs }), { mode: 0o600 });
      return true;
    } catch {
      runtime.state = 'lease_lost';
      void runtime.client.destroy();
      return false;
    }
  }

  private processAlive(pid: number): boolean {
    if (pid <= 0) return false;
    try {
      process.kill(pid, 0);
      return true;
    } catch {
      return false;
    }
  }

  private releaseLease(runtime: Runtime): void {
    try {
      const lease = JSON.parse(fs.readFileSync(runtime.leasePath, 'utf8')) as { owner?: string };
      if (lease.owner === this.instanceId) fs.unlinkSync(runtime.leasePath);
    } catch { /* an expired or replaced lease is not ours to remove */ }
  }

  private clearStaleChromiumProfileLocks(storageKey: string): void {
    const profile = path.resolve(this.config.authRoot, `session-${storageKey}`);
    if (!profile.startsWith(`${this.config.authRoot}${path.sep}`) || !fs.existsSync(profile)) return;

    // Lease ownership guarantees no healthy session worker should currently own this
    // profile. Any Chromium still referencing it is therefore an orphan from a
    // crashed/replaced worker and must be terminated before LocalAuth can reopen it.
    if (process.platform === 'linux' && fs.existsSync('/proc')) {
      for (const entry of fs.readdirSync('/proc')) {
        if (!/^\d+$/.test(entry)) continue;
        const pid = Number(entry);
        if (pid === process.pid) continue;
        try {
          const command = fs.readFileSync(`/proc/${pid}/cmdline`, 'utf8').replace(/\0/g, ' ');
          if (command.includes(profile) && /(chromium|chrome)/i.test(command)) {
            process.kill(pid, 'SIGKILL');
          }
        } catch {
          // Processes may exit between directory enumeration and inspection.
        }
      }
    }

    for (const name of ['SingletonLock', 'SingletonCookie', 'SingletonSocket']) {
      try { fs.rmSync(path.join(profile, name), { force: true }); } catch { /* recreated by Chromium when needed */ }
    }
  }

  private async safeEmit(uuid: string, event: string, payload: Record<string, unknown> = {}): Promise<void> {
    try { await this.callbacks.emit(uuid, event, payload); }
    catch (error) { console.error('callback delivery failed', { event, message: error instanceof Error ? error.message : 'unknown' }); }
  }

  private async forwardInboundMessage(input: SessionInput, message: Message): Promise<void> {
    if (!input.tenant_uuid || message.fromMe || !/^[1-9][0-9]{7,14}@c\.us$/.test(message.from) || !/^[1-9][0-9]{7,14}@c\.us$/.test(message.to)) return;
    if (!['chat', 'image', 'document', 'audio', 'ptt', 'video'].includes(message.type)) return;
    const type = message.type === 'chat' ? 'text' : message.type === 'ptt' ? 'audio' : message.type;
    const serializedId = message.id?._serialized;
    if (!serializedId || serializedId.length > 512) return;
    let media: Record<string, unknown> | null = null;
    if (message.hasMedia) {
      const downloaded = await message.downloadMedia();
      if (!downloaded) return;
      const bytes = Buffer.from(downloaded.data, 'base64');
      if (bytes.length < 1 || bytes.length > 16 * 1024 * 1024) return;
      media = { mime_type: downloaded.mimetype, size_bytes: bytes.length, checksum_sha256: crypto.createHash('sha256').update(bytes).digest('hex'), retrieval_reference: this.stableEventId(`${input.session_uuid}:media:${serializedId}`) };
    }
    const eventId = this.stableEventId(`${input.session_uuid}:inbox.message.received:${serializedId}`);
    await this.callbacks.emitInbox({
      event_id: eventId, event_type: 'inbox.message.received', occurred_at: new Date(message.timestamp * 1000).toISOString(), tenant_uuid: input.tenant_uuid, session_uuid: input.session_uuid,
      message: { whatsapp_message_id: serializedId.slice(0, 191), serialized_id: serializedId, from: message.from, to: message.to, type, body: type === 'text' ? message.body.slice(0, 65535) : null,
        caption: type !== 'text' ? message.body.slice(0, 65535) || null : null, reply_to_message_id: message.hasQuotedMsg ? (message as Message & { _data?: { quotedMsg?: { id?: { _serialized?: string } } } })._data?.quotedMsg?.id?._serialized?.slice(0, 191) ?? null : null,
        timestamp: new Date(message.timestamp * 1000).toISOString(), from_me: false, has_media: Boolean(media), media },
    });
  }

  private stableEventId(value: string): string {
    const hash = crypto.createHash('sha256').update(value).digest('hex');
    return `${hash.slice(0, 8)}-${hash.slice(8, 12)}-5${hash.slice(13, 16)}-a${hash.slice(17, 20)}-${hash.slice(20, 32)}`;
  }
}

function signatureHash(body: string): string { return crypto.createHash('sha256').update(body).digest('hex'); }
function allowedCampaignMime(mime: string): boolean {
  return /^(image\/(jpeg|png|webp)|video\/mp4|audio\/(mpeg|ogg|mp4)|application\/pdf|text\/plain|application\/(msword|vnd\.openxmlformats-officedocument\.(wordprocessingml\.document|spreadsheetml\.sheet|presentationml\.presentation)))$/.test(mime);
}
