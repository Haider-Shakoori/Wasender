# WhatsApp campaign UI

Batch 13 Part 5 completes the essential Blade/Tailwind/Alpine interface. Tenant users can list, filter, create, edit, review,
prepare, launch, pause, resume, cancel, duplicate, and monitor campaigns through the existing lifecycle services. The primary
detail page links lazy paginated recipient, exclusion, recipient-execution, and dispatch-attempt datasets and presents a
high-level event timeline.

The single campaign form uses a lightweight six-step Alpine presentation while submitting one server-validated request.
Audience choices are limited to saved tenant segments, groups, labels, and contacts; raw phone entry is unavailable.
Unavailable sessions are disabled. The review step reiterates consent and opt-out enforcement.

Active campaign pages poll the tenant-scoped status endpoint with bounded backoff. Its response contains only statuses,
aggregate counters, progress, safe warnings, allowed actions, and update time.

Platform operations include redacted campaign, preparation, execution, attempt, and connector-health pages. Authorized
reconciliation buttons call canonical reconciliation services and cannot force-send or force-success. Phones, content,
attachments, connector URLs, authentication data, raw payloads, and stack traces remain hidden.

Part 6 remains responsible for comprehensive browser, responsive, accessibility, permission, lifecycle, visual-regression,
performance, cross-browser, and release verification.
