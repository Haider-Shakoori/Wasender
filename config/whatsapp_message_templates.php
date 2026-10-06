<?php

return [
    'max_variables' => (int) env('WHATSAPP_TEMPLATE_MAX_VARIABLES', 25),
    'variable_default_max_length' => (int) env('WHATSAPP_TEMPLATE_VARIABLE_DEFAULT_MAX_LENGTH', 500),
    'body_max_length' => (int) env('WHATSAPP_TEMPLATE_BODY_MAX_LENGTH', 4096),
    'caption_max_length' => (int) env('WHATSAPP_TEMPLATE_CAPTION_MAX_LENGTH', 1024),
    'rendered_body_max_length' => (int) env('WHATSAPP_TEMPLATE_RENDERED_BODY_MAX_LENGTH', 4096),
    'rendered_caption_max_length' => (int) env('WHATSAPP_TEMPLATE_RENDERED_CAPTION_MAX_LENGTH', 1024),
    'variable_config_max_bytes' => (int) env('WHATSAPP_TEMPLATE_VARIABLE_CONFIG_MAX_BYTES', 16384),
    'value_max_length' => 1000,
    'parser_version' => (int) env('WHATSAPP_TEMPLATE_PARSER_VERSION', 1),
    'renderer_version' => (int) env('WHATSAPP_TEMPLATE_RENDERER_VERSION', 1),
    'attachment_disk' => env('WHATSAPP_TEMPLATE_ATTACHMENT_DISK', 'local'),
    'attachment_limits_mb' => ['image' => (int) env('WHATSAPP_TEMPLATE_IMAGE_MAX_MB', 10), 'document' => (int) env('WHATSAPP_TEMPLATE_DOCUMENT_MAX_MB', 25), 'audio' => (int) env('WHATSAPP_TEMPLATE_AUDIO_MAX_MB', 16), 'video' => (int) env('WHATSAPP_TEMPLATE_VIDEO_MAX_MB', 25)],
    'attachment_mimes' => ['image' => ['image/jpeg', 'image/png', 'image/webp'], 'document' => ['application/pdf', 'text/plain', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], 'audio' => ['audio/mpeg', 'audio/ogg', 'audio/mp4', 'audio/wav', 'audio/x-wav'], 'video' => ['video/mp4']],
    'temp_upload_retention_minutes' => (int) env('WHATSAPP_TEMPLATE_TEMP_UPLOAD_RETENTION_MINUTES', 60),
    'orphan_retention_hours' => (int) env('WHATSAPP_TEMPLATE_ORPHAN_RETENTION_HOURS', 24),
    'label_colors' => ['slate', 'red', 'orange', 'amber', 'green', 'teal', 'blue', 'indigo', 'violet', 'pink'],
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i:s',
    'datetime_format' => 'Y-m-d\TH:i:sP',
];
