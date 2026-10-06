# Campaign transport idempotency

Laravel creates one deterministic key per recipient attempt. Node persists the key before sending. Same-key/same-hash reuse
returns the stored result; changed payload or attempt correlation is a conflict. A successful key cannot send twice.
Callbacks have stable event IDs; Laravel's unique inbox stores their payload hash, so duplicates cannot consume usage or
increment counters twice. Interrupted sends remain unknown and block creation of another attempt.
