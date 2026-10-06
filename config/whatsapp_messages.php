<?php

return [
    'queue' => env('WHATSAPP_MESSAGE_QUEUE', 'whatsapp-messages'),
    'body_max' => 4096,
    'expires_minutes' => (int) env('WHATSAPP_MESSAGE_EXPIRES_MINUTES', 30),
    'max_attempts' => (int) env('WHATSAPP_MESSAGE_MAX_ATTEMPTS', 3),
    'attachment_disk' => env('WHATSAPP_ATTACHMENT_DISK', 'local'),
    'attachment_max_kb' => (int) env('WHATSAPP_ATTACHMENT_MAX_KB', 16384),
    'mimes' => [
        'image' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],
        'document' => ['application/pdf' => 'pdf', 'text/plain' => 'txt', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx'],
        'audio' => ['audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a'],
        'video' => ['video/mp4' => 'mp4'],
    ],
];
