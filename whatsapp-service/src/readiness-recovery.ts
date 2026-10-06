export type WhatsAppReadinessProbe = {
  socketState: string | null;
  hasSynced: boolean;
  bridgeInjected: boolean;
};

export const WHATSAPP_READY_RECOVERY_DELAYS_MS = [5_000, 10_000, 20_000] as const;

export function shouldRetryReadyInjection(probe: WhatsAppReadinessProbe): boolean {
  // WWebJS can exist after LoadUtils even when the rest of the client setup
  // (ClientInfo/interface/event listeners) has not completed. Readiness
  // recovery therefore keys off the WhatsApp socket being connected+synced,
  // while the SessionManager runtime state prevents retries after ready.
  return probe.socketState === 'CONNECTED' && probe.hasSynced;
}
