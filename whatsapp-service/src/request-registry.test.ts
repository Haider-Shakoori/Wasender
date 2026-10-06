import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
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

  it('marks interrupted sends unknown after restart instead of allowing a resend', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'wasender-request-registry-'));
    const filename = path.join(directory, 'requests.json');

    const first = new RequestRegistry(filename);
    first.begin('request-1', { body: 'hello' }, 'message-1');

    const restarted = new RequestRegistry(filename);
    const record = restarted.get('request-1');

    expect(record?.status).toBe('unknown');
    expect(record?.error).toBe('request_outcome_unknown');
    expect(restarted.begin('request-1', { body: 'hello' }, 'message-1').duplicate).toBe(true);

    fs.rmSync(directory, { recursive: true, force: true });
  });

  it('persists successful send results for reconciliation', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'wasender-request-registry-'));
    const filename = path.join(directory, 'requests.json');

    const first = new RequestRegistry(filename);
    first.begin('request-2', { body: 'hello' }, 'message-2');
    first.complete('request-2', { accepted: true, whatsapp_message_id: 'wamid-1' });

    const restarted = new RequestRegistry(filename);
    expect(restarted.get('request-2')?.result?.whatsapp_message_id).toBe('wamid-1');

    fs.rmSync(directory, { recursive: true, force: true });
  });
});
