<?php

return [
    'queues' => array_values(array_filter(array_map('trim', explode(',', env('OPERATIONS_QUEUES', 'default,whatsapp-sessions,whatsapp-messages,campaign-control,campaign-preparation,campaign-dispatch,campaign-retry,automation-control,automation-steps,automation-delays,integration-webhooks,contact-imports,inbox,chatbot'))))),
    'stale_minutes' => (int) env('OPERATIONS_STALE_MINUTES', 15),
];
