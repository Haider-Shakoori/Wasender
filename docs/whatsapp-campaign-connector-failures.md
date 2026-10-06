# Campaign connector failures

Failures are normalized as transient, permanent, policy, session, transport, attachment, recipient, internal, or unknown.
Temporary session/capacity/network/download failures retry only after confirmed non-send and Part 3 policy approval.
Bad addresses, tenant-session mismatch, restrictions, unsupported types, unsafe or mismatched media, invalid authentication,
and idempotency conflicts are permanent. Timeout after send begins is `transport_state_unknown`, never assumed failure.
