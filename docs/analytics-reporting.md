# Analytics reporting foundation

Analytics reads canonical tenant-scoped operational tables: transactional messages plus inbound-only inbox records, stored campaign counters, workflow executions, template usage references, conversations and agent activities, session state, and subscription usage snapshots. Outbound inbox projections are never counted as a second message.

Presets cover today, yesterday, 7/30 days, current/previous month, and tenant-timezone custom ranges up to 365 days; database boundaries are normalized to UTC. Metrics safely handle zero denominators, bounded daily series, top dimensions, and previous-period comparison. Tenant overview results cache for 120 seconds; privacy-safe platform results cache for 60 seconds with range and tenant identity in the key.

The tenant dashboard at `/app/analytics` combines overview, daily message activity, delivery, sessions, campaigns, automations, inbox, and plan usage. Date presets, tenant-timezone custom ranges, and tenant-owned session filtering use full-page GET requests. The platform dashboard at `/platform/analytics` provides global KPIs, daily volume, attention signals, and compact tenant usage reporting in UTC. Both dashboards use lightweight Blade/CSS charts and the existing authorization, subscription, theme, and responsive layout systems.

CSV exports remain deferred. Reliable first-response, resolution-time, session-uptime, detailed automation-message, and mutation metrics remain deferred because current canonical timestamps do not support them without inference or raw definition parsing. Automated dashboard tests are intentionally deferred until project-wide testing.
