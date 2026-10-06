import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import type { Request, Response, NextFunction } from 'express';

export function bodyHash(body: unknown): string {
  const serialized = typeof body === 'string' ? body : JSON.stringify(body ?? {});
  return crypto.createHash('sha256').update(serialized).digest('hex');
}

export function signature(secret: string, method: string, route: string, timestamp: string, nonce: string, body: unknown, requestId = '', idempotencyKey = ''): string {
  const values = [method.toUpperCase(), route, timestamp, nonce];
  if (requestId || idempotencyKey) values.push(requestId, idempotencyKey);
  values.push(bodyHash(body));
  const canonical = values.join('\n');
  return crypto.createHmac('sha256', secret).update(canonical).digest('hex');
}

export class NonceStore {
  private readonly seen = new Map<string, number>();
  constructor(private readonly ttlMs = 300_000, private readonly filename?: string) {
    if (filename) {
      try {
        const stored = JSON.parse(fs.readFileSync(filename, 'utf8')) as Record<string, number>;
        for (const [key, expiry] of Object.entries(stored)) if (expiry > Date.now()) this.seen.set(key, expiry);
      } catch { /* The first claim creates the replay store. */ }
    }
  }

  claim(nonce: string, now = Date.now()): boolean {
    for (const [key, expiresAt] of this.seen) if (expiresAt <= now) this.seen.delete(key);
    if (this.seen.has(nonce)) return false;
    this.seen.set(nonce, now + this.ttlMs);
    this.persist();
    return true;
  }

  private persist(): void {
    if (!this.filename) return;
    fs.mkdirSync(path.dirname(this.filename), { recursive: true });
    const temporary = `${this.filename}.${process.pid}.tmp`;
    fs.writeFileSync(temporary, JSON.stringify(Object.fromEntries(this.seen)), { mode: 0o600 });
    fs.renameSync(temporary, this.filename);
  }
}

export function verifySignature(secret: string, nonces = new NonceStore()) {
  return (request: Request, response: Response, next: NextFunction): void => {
    const timestamp = request.header('x-internal-timestamp') ?? '';
    const nonce = request.header('x-internal-nonce') ?? '';
    const supplied = request.header('x-internal-signature') ?? '';
    const requestId = request.header('x-internal-request-id') ?? '';
    const idempotencyKey = request.header('x-internal-idempotency-key') ?? '';
    const suppliedHash = request.header('x-internal-content-sha256');
    const parsed = Number(timestamp);
    if (!nonce || !Number.isFinite(parsed) || Math.abs(Date.now() - parsed * 1000) > 300_000) {
      response.status(401).json({ error: 'invalid_signature_timestamp' }); return;
    }
    const signingBody = request.method === 'GET' && !request.header('content-length') ? '' : request.body;
    if (suppliedHash && suppliedHash !== bodyHash(signingBody)) {
      response.status(401).json({ error: 'invalid_content_hash' }); return;
    }
    const expected = signature(secret, request.method, request.originalUrl, timestamp, nonce, signingBody, requestId, idempotencyKey);
    const valid = supplied.length === expected.length &&
      crypto.timingSafeEqual(Buffer.from(supplied), Buffer.from(expected));
    if (!valid || !nonces.claim(nonce)) {
      response.status(401).json({ error: valid ? 'replayed_request' : 'invalid_signature' }); return;
    }
    next();
  };
}
