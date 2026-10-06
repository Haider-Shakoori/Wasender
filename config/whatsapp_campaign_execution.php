<?php

return [
    'enabled' => (bool) env('WHATSAPP_CAMPAIGN_EXECUTION_ENABLED', true), 'claim_size' => (int) env('WHATSAPP_CAMPAIGN_EXECUTION_CLAIM_SIZE', 100),
    'tenant_concurrency' => (int) env('WHATSAPP_CAMPAIGN_TENANT_CONCURRENCY', 2), 'campaign_concurrency' => (int) env('WHATSAPP_CAMPAIGN_CONCURRENCY', 20),
    'session_concurrency' => (int) env('WHATSAPP_CAMPAIGN_SESSION_CONCURRENCY', 1), 'max_attempts' => (int) env('WHATSAPP_CAMPAIGN_MAX_ATTEMPTS', 3),
    'retry_base_seconds' => (int) env('WHATSAPP_CAMPAIGN_RETRY_BASE_SECONDS', 30), 'retry_max_seconds' => (int) env('WHATSAPP_CAMPAIGN_RETRY_MAX_SECONDS', 900),
    'retry_jitter_percent' => (int) env('WHATSAPP_CAMPAIGN_RETRY_JITTER_PERCENT', 10), 'stale_minutes' => (int) env('WHATSAPP_CAMPAIGN_EXECUTION_STALE_MINUTES', 15),
    'claim_timeout_minutes' => (int) env('WHATSAPP_CAMPAIGN_RECIPIENT_CLAIM_TIMEOUT_MINUTES', 5), 'schedule_poll_seconds' => (int) env('WHATSAPP_CAMPAIGN_SCHEDULE_POLL_SECONDS', 60),
    'max_runtime_hours' => (int) env('WHATSAPP_CAMPAIGN_MAX_RUNTIME_HOURS', 24),
    'queues' => ['control' => 'campaign-control', 'dispatch' => 'campaign-dispatch', 'retry' => 'campaign-retry', 'reconciliation' => 'campaign-reconciliation'],
];
