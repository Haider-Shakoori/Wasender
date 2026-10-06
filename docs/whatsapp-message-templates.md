# WhatsApp message templates

Batch 17 Part 1 provides the tenant-scoped template and version domain. A template owns immutable historical versions and points to at most one active draft and one current published version.

New templates begin with draft version 1. Draft content and template metadata use optimistic locking. Publishing transactionally promotes the draft, supersedes the prior published version, updates both pointers, and is idempotent. Published and superseded content cannot be updated or deleted through normal model/service flows. Creating a draft after publication copies the current published content into the next monotonically numbered version without modifying history.

Archiving preserves every version and blocks mutation. Restoring selects `published` when a published pointer exists and otherwise `draft`; it does not change version content. Duplication creates a new UUID and draft version 1 without publication or audit history.

Tenant permissions are `whatsapp_templates.view`, `create`, `update`, `publish`, `duplicate`, `archive`, `restore`, and `view_versions`. Platform access is read-only through `platform.whatsapp_templates.view` and `platform.whatsapp_templates.view_versions`. Mutation requires the `whatsapp_message_templates` subscription feature; total and published limits are supported.

Batch 17 Part 2 supports only `{{variable_name}}` placeholders. Optional surrounding whitespace is canonicalized. Registered contact variables are `first_name`, `last_name`, `full_name`, `display_name`, `company`, `phone`, `email`, `preferred_language`, and `timezone`; generic date variables are `current_date`, `current_time`, and `current_datetime`. Variables explicitly belong to `contact` or `generic` contexts.

Each used variable requires configuration with a boolean `required` flag and an optional bounded scalar default. Rendering uses explicit scalar values, then defaults, then an empty string with a warning for missing optional values. Missing required values fail. Dotted/property syntax, methods, functions, filters, operators, nested/triple braces, Blade raw tags, unknown names, and unresolved placeholders are rejected. Nothing is executed and the renderer performs no database or network access.

Preview uses fictional registry values and bounded overrides; it does not query contacts, persist usage, modify versions, or send messages. Canonical SHA-256 content hashes cover type, canonical content/configuration, context, parser/renderer versions, and content configuration. Render hashes additionally cover resolved values and rendered text. Injected time makes date rendering deterministic.

Publishing now requires successful parsing and validation. Variable configuration, hashes, and parser/renderer versions are immutable after publication and are copied safely into new drafts; duplication copies them into a new draft version 1.

Parts 4–5 remain deferred: campaign/transactional integration and final tenant/platform UI with release validation are not implemented here.

Batch 17 Part 3 adds private version-owned media for image, document, audio, and video templates. Files use server-generated tenant/template paths on the private disk; server-detected MIME, size, SHA-256 checksum, safe filename, and media category are stored. Text templates cannot accept media. Media templates cannot publish without a compatible stored attachment. No permanent public URL is generated.

Draft media can be replaced or removed with optimistic locking. New files are stored and verified before database replacement; failed files and unreferenced replaced files are removed. Published and superseded attachment records are immutable. New drafts and duplicates create a new ownership record that safely reuses the immutable stored file, so replacing a draft never changes published history. Attachment metadata participates in the canonical content hash.

Tenant categories provide one optional primary category per template. Reusable tenant labels use a deduplicated assignment pivot and approved color tokens. Categories and labels can be created, updated, archived, assigned, searched, and filtered without becoming message payload. Tenant queries remain paginated and support category, label, attachment, publication, archive, type, status, date, and allowlisted sort filters; platform filters remain read-only.

Part 4 remains the integration boundary for campaign and transactional-message snapshots and secure transport references. Part 5 UI and release validation are not implemented.
# Tenant and platform UI

Tenants can list, filter, create, edit, preview, validate, publish, duplicate, archive, restore, organize, and inspect versioned templates. Draft media controls expose only verified filename, MIME, size, and checksum state. Published content and media remain immutable.

Published templates can be applied to editable campaigns through the canonical snapshot service and selected from the existing transactional compose screen. Campaign recipient rendering and transactional delivery continue through the established queues and transport.

Platform template pages are read-only and expose safe metadata, version state, attachment verification state, and real usage counts without storage paths, tokens, recipients, resolved values, or rendered campaign content.

Known limitations: selectors are intentionally compact, campaign variable overrides use the existing safe-value endpoint, previews are fictional, and advanced analytics, translation, marketplace, import/export, and automation are deferred. Release validation covered focused template tests, compiled Blade views, routes, formatting, migrations, and the production asset build.
