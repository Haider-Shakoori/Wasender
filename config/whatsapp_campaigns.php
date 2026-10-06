<?php

return [
    'enabled' => (bool) env('WHATSAPP_CAMPAIGNS_ENABLED', true),
    'name_max' => (int) env('WHATSAPP_CAMPAIGN_NAME_MAX_LENGTH', 150),
    'description_max' => (int) env('WHATSAPP_CAMPAIGN_DESCRIPTION_MAX_LENGTH', 1000),
    'body_max' => (int) env('WHATSAPP_CAMPAIGN_BODY_MAX_LENGTH', 4096),
    'max_schedule_days' => (int) env('WHATSAPP_CAMPAIGN_MAX_SCHEDULE_DAYS', 365),
    'schedule_grace_minutes' => 5,
    'default_timezone' => env('WHATSAPP_CAMPAIGN_DEFAULT_TIMEZONE', 'UTC'),
    'max_selected_sessions' => (int) env('WHATSAPP_CAMPAIGN_MAX_SELECTED_SESSIONS', 5),
    'max_manual_contacts' => (int) env('WHATSAPP_CAMPAIGN_MAX_MANUAL_CONTACTS', 500),
    'attachment_disk' => env('WHATSAPP_CAMPAIGN_ATTACHMENT_DISK', 'local'),
    'attachment_max_kb' => 16384,
    'preparation_chunk_size' => (int) env('WHATSAPP_CAMPAIGN_PREPARATION_CHUNK_SIZE', 500),
    'preparation_lead_minutes' => (int) env('WHATSAPP_CAMPAIGN_PREPARATION_LEAD_MINUTES', 5),
    'preparation_stale_minutes' => (int) env('WHATSAPP_CAMPAIGN_PREPARATION_STALE_MINUTES', 30),
    'preparation_max_retries' => (int) env('WHATSAPP_CAMPAIGN_PREPARATION_MAX_RETRIES', 3),
    'estimate_cache_seconds' => (int) env('WHATSAPP_CAMPAIGN_ESTIMATE_CACHE_SECONDS', 60),
    'exclusion_retention_days' => (int) env('WHATSAPP_CAMPAIGN_EXCLUSION_RETENTION_DAYS', 90),
    'failed_preparation_retention_days' => (int) env('WHATSAPP_CAMPAIGN_FAILED_PREPARATION_RETENTION_DAYS', 30),
];
