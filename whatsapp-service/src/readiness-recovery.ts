export type WhatsAppReadinessProbe = {
  socketState: string | null;
  hasSynced: boolean;
  bridgeInjected: boolean;
};

export const WHATSAPP_READY_RECOVERY_DELAYS_MS = [5_000, 10_000, 20_000] as const;

export function shouldRetryReadyInjection(probe: WhatsAppReadinessProbe): boolean {
  return probe.socketState === 'CONNECTED' && probe.hasSynced && !probe.bridgeInjected;
}
