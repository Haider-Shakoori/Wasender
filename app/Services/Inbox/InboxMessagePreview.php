<?php

namespace App\Services\Inbox;

use Illuminate\Support\Str;

final class InboxMessagePreview
{
    public function make(string $type, ?string $body, ?string $caption = null): string
    {
        if ($type === 'text') {
            return Str::limit(trim((string) $body), 240, '…');
        }

        return match ($type) {
            'image' => 'Image', 'document' => 'Document', 'audio' => 'Audio', 'video' => 'Video', default => 'Message'
        }.(filled($caption) ? ': '.Str::limit(trim((string) $caption), 180, '…') : '');
    }
}
