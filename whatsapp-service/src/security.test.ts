import { describe, expect, it } from 'vitest';
import { vi } from 'vitest';
import { NonceStore, bodyHash, signature, verifySignature } from './security.js';

describe('connector request security', () => {
  it('produces a stable canonical HMAC', () => {
    expect(signature('secret', 'post', '/internal/sessions', '100', 'nonce', { a: 1 }))
      .toBe(signature('secret', 'POST', '/internal/sessions', '100', 'nonce', { a: 1 }));
    expect(bodyHash('')).toHaveLength(64);
  });

  it('rejects a nonce replay until expiry', () => {
    const store = new NonceStore(100);
    expect(store.claim('same', 1000)).toBe(true);
    expect(store.claim('same', 1050)).toBe(false);
    expect(store.claim('same', 1101)).toBe(true);
  });

  it('rejects invalid and expired signed requests before routing', () => {
    const secret = 's'.repeat(32);
    const response = { status: vi.fn().mockReturnThis(), json: vi.fn() };
    const next = vi.fn();
    const invalid = { method: 'POST', originalUrl: '/internal/test', body: {}, header: (name: string) => ({
      'x-internal-timestamp': Math.floor(Date.now() / 1000).toString(), 'x-internal-nonce': 'nonce-invalid',
      'x-internal-signature': '0'.repeat(64),
    }[name.toLowerCase()] ?? '') };
    verifySignature(secret)(invalid as never, response as never, next);
    expect(response.status).toHaveBeenLastCalledWith(401);
    expect(next).not.toHaveBeenCalled();

    response.status.mockClear();
    const timestamp = Math.floor(Date.now() / 1000 - 600).toString();
    const expired = { method: 'POST', originalUrl: '/internal/test', body: {}, header: (name: string) => ({
      'x-internal-timestamp': timestamp, 'x-internal-nonce': 'nonce-expired',
      'x-internal-signature': signature(secret, 'POST', '/internal/test', timestamp, 'nonce-expired', {}),
    }[name.toLowerCase()] ?? '') };
    verifySignature(secret)(expired as never, response as never, next);
    expect(response.status).toHaveBeenLastCalledWith(401);
  });
});
