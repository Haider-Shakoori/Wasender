# Campaign media delivery

Attachments remain private. Laravel issues an encrypted, short-lived token bound to purpose, tenant, campaign, execution,
attempt, attachment UUID, checksum, and expiry. Node also signs retrieval with the internal HMAC. Laravel revalidates every
binding and streams exact MIME/length with `no-store` and `nosniff`. Node checks origin, route, timeout, size, MIME allowlist,
byte length, and SHA-256 before constructing `MessageMedia`. Names become basenames and never storage paths.
