<?php

return [
    'queue' => env('WHATSAPP_CHATBOT_QUEUE', 'chatbot'),
    'max_rules' => 50,
    'match_value_max_length' => 500,
    'reply_text_max_length' => 4096,
    'default_cooldown_seconds' => 2,
    'max_replies_per_conversation_per_minute' => 10,
];
