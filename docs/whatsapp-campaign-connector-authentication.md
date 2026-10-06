# Campaign connector authentication

Requests and callbacks use the canonical connector HMAC. Campaign signatures bind method, path, Unix timestamp, nonce,
request ID, idempotency/event ID, and the SHA-256 of the exact body. Both sides enforce bounded timestamps, constant-time
comparison, content-hash checks, and replay protection. Production Laravel uses its shared cache for nonce durability.
Secrets, signatures, tokens, bodies, phones, browser paths, and session credentials are never operational log fields.
