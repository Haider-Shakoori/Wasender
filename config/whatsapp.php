<?php

return [
    'connector_url' => env('WHATSAPP_CONNECTOR_URL', 'http://whatsapp:3100'),
    'hmac_secret' => env('WHATSAPP_HMAC_SECRET'),
    'callback_key' => env('WHATSAPP_CALLBACK_KEY'),
    'request_timeout_seconds' => (int) env('WHATSAPP_REQUEST_TIMEOUT', 8),
    'signature_tolerance_seconds' => (int) env('WHATSAPP_SIGNATURE_TOLERANCE', 300),
    'qr_ttl_seconds' => (int) env('WHATSAPP_QR_TTL', 90),
    'max_reconnect_attempts' => (int) env('WHATSAPP_MAX_RECONNECT_ATTEMPTS', 5),
    'stale_after_seconds' => (int) env('WHATSAPP_STALE_AFTER', 120),
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp-sessions'),
    'recovery_batch_size' => (int) env('WHATSAPP_RECOVERY_BATCH_SIZE', 200),
    'recovery_stagger_seconds' => (int) env('WHATSAPP_RECOVERY_STAGGER_SECONDS', 2),
];
