import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { loadConfig } from './config.js';

const original = { ...process.env };

describe('connector capacity configuration', () => {
  beforeEach(() => {
    process.env = {
      ...original,
      WHATSAPP_HMAC_SECRET: 'test-secret',
      LARAVEL_CALLBACK_URL: 'http://localhost/internal/whatsapp/events',
    };
  });

  afterEach(() => {
    process.env = { ...original };
  });

  it('uses safe worker and disk defaults', () => {
    delete process.env.SESSION_WORKER_MAX_ACTIVE;
    delete process.env.WHATSAPP_AUTH_DISK_CRITICAL_PERCENT;

    const config = loadConfig();

    expect(config.sessionWorkers.maxActive).toBe(50);
    expect(config.sessionWorkers.diskCriticalPercent).toBe(95);
  });

  it('normalizes invalid boundary values', () => {
    process.env.SESSION_WORKER_MAX_ACTIVE = '0';
    process.env.WHATSAPP_AUTH_DISK_CRITICAL_PERCENT = '150';

    const config = loadConfig();

    expect(config.sessionWorkers.maxActive).toBe(1);
    expect(config.sessionWorkers.diskCriticalPercent).toBe(100);
  });
});
