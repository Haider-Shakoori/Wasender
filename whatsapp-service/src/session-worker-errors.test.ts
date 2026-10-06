import { describe, expect, it } from 'vitest';
import { isTransientBrowserNavigationRejection } from './session-worker-errors.js';

describe('session worker browser rejection handling', () => {
  it('keeps the worker alive for expected WhatsApp navigation races', () => {
    expect(isTransientBrowserNavigationRejection(new Error('Execution context was destroyed, most likely because of a navigation.'))).toBe(true);
    expect(isTransientBrowserNavigationRejection(new Error('Cannot find context with specified id 42'))).toBe(true);
  });

  it('does not hide unrelated failures', () => {
    expect(isTransientBrowserNavigationRejection(new Error('Protocol error: Target closed'))).toBe(false);
    expect(isTransientBrowserNavigationRejection('unexpected failure')).toBe(false);
  });
});
