<?php

return ['integration_signature_tolerance_seconds' => (int) env('INTEGRATION_SIGNATURE_TOLERANCE_SECONDS', 300), 'rates' => ['integration_messages' => (int) env('RATE_INTEGRATION_MESSAGES_PER_MINUTE', 60), 'integration_events' => (int) env('RATE_INTEGRATION_EVENTS_PER_MINUTE', 120), 'inbox_replies' => (int) env('RATE_INBOX_REPLIES_PER_MINUTE', 60), 'automation_manual' => (int) env('RATE_AUTOMATION_MANUAL_PER_MINUTE', 30), 'chatbot_state' => (int) env('RATE_CHATBOT_STATE_PER_MINUTE', 30), 'template_preview' => (int) env('RATE_TEMPLATE_PREVIEW_PER_MINUTE', 30)]];
