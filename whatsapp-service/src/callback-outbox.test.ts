import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { describe, expect, it } from 'vitest';
import { CallbackOutbox } from './callback-outbox.js';

describe('callback outbox', () => {
  it('persists undelivered callbacks across process restarts', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'wasender-callback-outbox-'));
    const filename = path.join(directory, 'callbacks.json');

    const first = new CallbackOutbox(filename);
    first.put({
      id: 'event-1',
      kind: 'inbox',
      target: 'http://localhost/internal/whatsapp/inbox-events',
      payload: { event_id: 'event-1' },
      attempts: 0,
      nextAttemptAt: Date.now(),
      expiresAt: Date.now() + 60_000,
    });

    const restarted = new CallbackOutbox(filename);
    expect(restarted.count()).toBe(1);
    expect(restarted.get('event-1')?.kind).toBe('inbox');

    restarted.delivered('event-1');
    expect(new CallbackOutbox(filename).count()).toBe(0);

    fs.rmSync(directory, { recursive: true, force: true });
  });

  it('keeps retry state durable', () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'wasender-callback-outbox-'));
    const filename = path.join(directory, 'callbacks.json');

    const outbox = new CallbackOutbox(filename);
    outbox.put({
      id: 'event-2',
      kind: 'message',
      target: 'http://localhost/internal/whatsapp/message-events',
      payload: { event_id: 'event-2' },
      attempts: 0,
      nextAttemptAt: Date.now(),
      expiresAt: Date.now() + 60_000,
    });
    outbox.retry('event-2', 5000);

    const restarted = new CallbackOutbox(filename);
    expect(restarted.get('event-2')?.attempts).toBe(1);

    fs.rmSync(directory, { recursive: true, force: true });
  });
});
