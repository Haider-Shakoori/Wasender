<?php

return [
    'enabled' => (bool) env('WHATSAPP_CAMPAIGN_NODE_TRANSPORT_ENABLED', true),
    'connect_timeout_seconds' => (int) env('WHATSAPP_CAMPAIGN_NODE_CONNECT_TIMEOUT_SECONDS', 5),
    'request_timeout_seconds' => (int) env('WHATSAPP_CAMPAIGN_NODE_REQUEST_TIMEOUT_SECONDS', 70),
    'lookup_timeout_seconds' => (int) env('WHATSAPP_CAMPAIGN_NODE_LOOKUP_TIMEOUT_SECONDS', 10),
    'unknown_timeout_minutes' => (int) env('WHATSAPP_CAMPAIGN_TRANSPORT_UNKNOWN_TIMEOUT_MINUTES', 30),
    'reconcile_batch' => (int) env('WHATSAPP_CAMPAIGN_TRANSPORT_RECONCILE_BATCH', 100),
    'attachment_token_ttl_seconds' => (int) env('WHATSAPP_CAMPAIGN_ATTACHMENT_TOKEN_TTL_SECONDS', 120),
];
