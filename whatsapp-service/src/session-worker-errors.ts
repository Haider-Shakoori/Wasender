export function isTransientBrowserNavigationRejection(reason: unknown): boolean {
  const message = reason instanceof Error ? reason.message : String(reason ?? '');

  return [
    'Execution context was destroyed, most likely because of a navigation.',
    'Cannot find context with specified id',
  ].some((fragment) => message.includes(fragment));
}
