<?php

namespace App\Services\Templates;

use App\Enums\WhatsAppMessageTemplateType;
use App\Models\WhatsAppMessageTemplateVersion;

final class WhatsAppMessageTemplateContentHasher
{
    public function hash(WhatsAppMessageTemplateVersion $version, WhatsAppMessageTemplateType $type, ?string $canonicalBody = null, ?string $canonicalCaption = null, ?array $configuration = null): string
    {
        $attachment = $version->relationLoaded('attachment') ? $version->attachment : null;

        return $this->hashArray([
            'type' => $type->value, 'body' => $canonicalBody ?? $version->body, 'caption' => $canonicalCaption ?? $version->caption,
            'variable_context' => $version->variable_context->value, 'variable_configuration' => $configuration ?? ($version->variable_configuration ?? []),
            'parser_version' => $version->parser_version, 'renderer_version' => $version->renderer_version,
            'content_configuration' => $version->content_configuration ?? [],
            'attachment' => $attachment ? ['checksum_sha256' => $attachment->checksum_sha256, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes, 'media_category' => $attachment->media_category, 'safe_name' => $attachment->safe_name] : null,
        ]);
    }

    public function hashArray(array $values): string
    {
        return hash('sha256', json_encode($this->canonical($values), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private function canonical(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values, SORT_STRING);
        }
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->canonical($value);
            }
        }

        return $values;
    }
}
