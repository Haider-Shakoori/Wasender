# Automation workflows

Batch 18 Part 1 establishes tenant-owned workflows, immutable published versions, and ordered step definitions. A workflow begins with version 1 as its active draft. Publishing clears the draft pointer, preserves historical versions, supersedes the prior publication, and does not enable the workflow automatically.

Part 2 adds publish-readiness validation and deterministic definition hashing. Supported triggers are `manual`, contact create/update/group/label events, consent grant/withdrawal, and a scheduled local datetime with an IANA timezone. Other reserved triggers remain stored enum values but cannot be published in the current version. Tenant-owned group and label references are checked against the active tenant.

Conditions use a versioned, allowlisted schema with bounded AND/OR groups, approved field/operator combinations, and trigger-compatible fields. Steps support bounded fixed-duration delays, binary condition/branch targets, sequential references, and terminal stops. Publication requires exactly one entry, valid references, an acyclic reachable graph, and configured step/depth limits. Successful validation stores a SHA-256 hash over canonical semantic definition data; draft edits invalidate that result.

Tenant permissions cover viewing, creation, updates, publication, enable/disable, duplication, archive/restore, and version history. Mutations require the `automations.access` subscription feature and workflow capacity. Platform access is read-only.

Part 3 supplies the minimal execution engine described below; Part 4 will add action behavior.

## Minimal execution engine

Part 3 adds tenant-scoped manual executions bound to one validated published version and definition hash. Starts are idempotent per tenant, enforce subscription and active-run limits, and process one step per bounded queue job. Conditions use the Part 2 allowlist; branches persist their selected path; fixed delays persist a due time and resume through a delayed job or reconciliation; stop and natural path completion are terminal.

Execution history and step attempts are retained. Duplicate jobs are guarded by execution locks and deterministic attempt keys. Runtime and processed-step limits protect historical definitions. Cancellation is idempotent, waiting work is cancelled, and `automations:reconcile-executions` repairs due or stale runs in bounded batches. The retry policy is bounded to transient infrastructure failures.

Part 4 adds the initial trusted action handlers described below. Contact-event listeners, scheduled triggers, deferred action types, Node changes, and the final UI remain unavailable.

## Core actions

Part 4 registers trusted handlers for WhatsApp template sending, contact label/group add and remove, approved contact updates, and workflow stop. Unsupported reserved actions block publication. Action definitions use strict tenant-owned UUID references and bounded value mappings from contact, trigger, execution context, or constants.

Template actions freeze the selected published template version and content hash when the workflow publishes. Runtime rendering reuses the immutable Batch 17 renderer and the existing transactional message service, including its queue, transport idempotency, usage accounting, consent checks, and message history. The workflow completes this action when the canonical pipeline accepts the message; it does not wait for delivery or read acknowledgements.

Label and group mutations are idempotent and never delete resources. Generic contact updates are restricted to first name, last name, company, preferred language, and timezone; phone, consent, opt-out, suppression, and blocked state cannot be changed. Cancellation is rechecked before mutation, safe outputs contain identifiers or changed field names only, and transient failures use the bounded Part 3 retry policy. Webhooks, notifications, internal tasks, workflow chaining, plain-message actions, and Node changes remain deferred.

## End-user interface

Part 5 completes the module with tenant workflow lists, a four-section manual workflow editor, ordered step controls, workflow/version/execution details, manual runs with contact selection, cancellation warnings, and safe execution timelines. Platform operators have read-only workflow and execution pages. Raw definitions, execution context, message content, personal data, and secrets are not displayed.
