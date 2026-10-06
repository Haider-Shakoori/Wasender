import { describe, expect, it } from 'vitest';
import { RequestRegistry } from './request-registry.js';

describe('message request registry', () => {
  it('deduplicates identical requests', () => {
    const registry = new RequestRegistry();
    expect(registry.begin('request', { recipient: '15551234567' }, 'message').duplicate).toBe(false);
    expect(registry.begin('request', { recipient: '15551234567' }, 'message').duplicate).toBe(true);
  });
  it('rejects changed payload reuse', () => {
    const registry = new RequestRegistry();
    registry.begin('request', { body: 'one' }, 'message');
    expect(() => registry.begin('request', { body: 'two' }, 'message')).toThrow('request_id_payload_mismatch');
  });
});
