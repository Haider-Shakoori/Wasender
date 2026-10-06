# Campaign transport reconciliation

`whatsapp-campaigns:reconcile-transport` uses a lock and bounded batch to inspect stale transport-pending or unknown attempts.
It performs an authenticated lookup using the original tenant, attempt UUID, and idempotency key. An authoritative message ID
completes that attempt; a confirmed pre-send failure enters retry policy; unresolved results remain unknown until the bounded
window expires. No response loss can directly trigger a second send. Finalization requires no open recipients or unknowns.
