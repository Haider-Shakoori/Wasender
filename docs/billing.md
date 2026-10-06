# Billing

Plans are database-driven and define feature access, resource limits, billing interval, price metadata, trial days, and grace days. Each tenant has one canonical current subscription with historical status changes retained.

Message usage is measured from the canonical outbound message record within the subscription period. The central message service checks subscription access, the `messages.send` entitlement, and remaining capacity while locking the subscription so concurrent sends cannot exceed the limit.

Tenants can review their plan, status, period, message usage, essential feature limits, and recent payments at `/app/billing`. Expired or cancelled subscriptions preserve data but block restricted operations.

Authorized platform administrators can manage plans and tenant subscriptions, extend periods, change lifecycle status, and record manual payments. Payment records contain no card or banking credentials.

Online gateways, checkout, tax, coupons, proration, invoices, refunds, dunning, and currency conversion are deferred.
