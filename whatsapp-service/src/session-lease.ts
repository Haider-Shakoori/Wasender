export type SessionLease = {
  owner?: string;
  host?: string;
  pid?: number;
  expires_at?: number;
};

export function canReclaimSessionLease(
  lease: SessionLease,
  currentOwner: string,
  currentHost: string,
  processAlive: (pid: number) => boolean,
  now = Date.now(),
): boolean {
  if ((lease.expires_at ?? 0) <= now) return true;

  if (lease.owner === currentOwner && lease.host !== currentHost) {
    return true;
  }

  if (lease.host === currentHost && Number.isInteger(lease.pid) && !processAlive(lease.pid!)) {
    return true;
  }

  return false;
}
