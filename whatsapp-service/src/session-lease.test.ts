import { describe, expect, it } from 'vitest';
import { canReclaimSessionLease } from './session-lease.js';

describe('session lease recovery', () => {
  it('reclaims an expired lease', () => {
    expect(canReclaimSessionLease(
      { owner: 'connector:abc', host: 'old', pid: 10, expires_at: 1000 },
      'connector:abc',
      'new',
      () => true,
      1001,
    )).toBe(true);
  });

  it('reclaims the same logical worker lease after container replacement', () => {
    expect(canReclaimSessionLease(
      { owner: 'connector:abc', host: 'old-container', pid: 24, expires_at: 999999 },
      'connector:abc',
      'new-container',
      () => true,
      1000,
    )).toBe(true);
  });

  it('keeps an unexpired foreign lease', () => {
    expect(canReclaimSessionLease(
      { owner: 'other:abc', host: 'other-container', pid: 24, expires_at: 999999 },
      'connector:abc',
      'new-container',
      () => false,
      1000,
    )).toBe(false);
  });

  it('reclaims a dead same-host process lease', () => {
    expect(canReclaimSessionLease(
      { owner: 'connector:abc', host: 'same-container', pid: 24, expires_at: 999999 },
      'connector:abc',
      'same-container',
      () => false,
      1000,
    )).toBe(true);
  });
});
