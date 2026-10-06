import { describe, expect, it } from 'vitest';
import {
  shouldRetryReadyInjection,
  WHATSAPP_READY_RECOVERY_DELAYS_MS,
} from './readiness-recovery.js';

describe('WhatsApp ready recovery', () => {
  it('retries injection only after WhatsApp is connected and synced', () => {
    expect(shouldRetryReadyInjection({
      socketState: 'CONNECTED',
      hasSynced: true,
      bridgeInjected: false,
    })).toBe(true);

    expect(shouldRetryReadyInjection({
      socketState: 'OPENING',
      hasSynced: true,
      bridgeInjected: false,
    })).toBe(false);

    expect(shouldRetryReadyInjection({
      socketState: 'CONNECTED',
      hasSynced: false,
      bridgeInjected: false,
    })).toBe(false);
  });

  it('does not reinject after the WWebJS bridge exists', () => {
    expect(shouldRetryReadyInjection({
      socketState: 'CONNECTED',
      hasSynced: true,
      bridgeInjected: true,
    })).toBe(false);
  });

  it('uses bounded backoff instead of retrying forever', () => {
    expect(WHATSAPP_READY_RECOVERY_DELAYS_MS).toEqual([5_000, 10_000, 20_000]);
  });
});
