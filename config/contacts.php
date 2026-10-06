<?php

return ['import_disk' => env('WHATSAPP_CONTACT_IMPORT_DISK', 'local'), 'import_max_mb' => (int) env('WHATSAPP_CONTACT_IMPORT_MAX_FILE_MB', 10), 'import_max_rows' => (int) env('WHATSAPP_CONTACT_IMPORT_MAX_ROWS', 50000), 'import_chunk_size' => (int) env('WHATSAPP_CONTACT_IMPORT_CHUNK_SIZE', 500), 'preview_rows' => (int) env('WHATSAPP_CONTACT_IMPORT_PREVIEW_ROWS', 100), 'retention_days' => (int) env('WHATSAPP_CONTACT_IMPORT_RETENTION_DAYS', 30), 'segment_max_rules' => (int) env('WHATSAPP_CONTACT_SEGMENT_MAX_RULES', 25)];
