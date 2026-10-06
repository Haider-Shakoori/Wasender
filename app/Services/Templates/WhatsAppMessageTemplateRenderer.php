<?php

namespace App\Services\Templates;

use App\Data\Templates\RenderedWhatsAppTemplateAttachment;
use App\Data\Templates\RenderedWhatsAppTemplatePayload;
use App\Data\Templates\WhatsAppTemplateRenderContext;
use App\Data\Templates\WhatsAppTemplateVariableConfiguration;
use App\Models\WhatsAppMessageTemplateVersion;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageTemplateRenderer
{
    public function __construct(private WhatsAppTemplateVariableRegistry $registry, private WhatsAppTemplatePlaceholderParser $parser, private ValidateWhatsAppMessageTemplateVersionService $validator, private WhatsAppMessageTemplateContentHasher $hasher) {}

    public function render(WhatsAppMessageTemplateVersion $version, WhatsAppTemplateRenderContext $context): RenderedWhatsAppTemplatePayload
    {
        if (! $version->relationLoaded('template')) {
            throw new \LogicException('Template relation must be preloaded; the renderer never queries the database.');
        }
        $template = $version->template;
        if ($version->variable_context !== $context->context) {
            throw ValidationException::withMessages(['context' => 'Render context does not match the template version.']);
        }
        $validation = $this->validator->validate($version, $template->type);
        if (! $validation->valid()) {
            throw ValidationException::withMessages(['template' => array_map(fn ($error) => $error['code'], $validation->errors)]);
        }
        $configuration = WhatsAppTemplateVariableConfiguration::normalize($version->variable_configuration ?? [], $context->context, $this->registry)->values;
        foreach (array_keys($context->values) as $key) {
            $definition = $this->registry->find($key);
            if (! $definition || ! $definition->supports($context->context)) {
                throw ValidationException::withMessages(['values' => "Unknown render variable: {$key}"]);
            }
        }
        $now = ($context->now ?? CarbonImmutable::now($context->timezone))->setTimezone($context->timezone);
        $provided = $context->values;
        $provided['current_date'] = $now->format(config('whatsapp_message_templates.date_format'));
        $provided['current_time'] = $now->format(config('whatsapp_message_templates.time_format'));
        $provided['current_datetime'] = $now->format(config('whatsapp_message_templates.datetime_format'));
        $resolved = [];
        $warnings = [];
        foreach ($validation->variables as $key) {
            $definition = $configuration[$key];
            $value = $provided[$key] ?? $definition['default'];
            if ($value === null && $definition['required']) {
                throw ValidationException::withMessages(['values.'.$key => 'A required template variable is missing.']);
            }
            if ($value === null) {
                $value = '';
                $warnings[] = ['code' => 'optional_variable_empty', 'variable' => $key];
            }
            $resolved[$key] = $this->normalize($key, $value, $context->timezone);
        }
        ksort($resolved, SORT_STRING);
        $parsed = $this->parser->parse($version->body, $version->caption, $context->context);
        $body = $this->substitute($parsed->body, $resolved);
        $caption = $this->substitute($parsed->caption, $resolved);
        if (str_contains($body ?? '', '{{') || str_contains($caption ?? '', '{{')) {
            throw ValidationException::withMessages(['template' => 'An unresolved placeholder remains.']);
        }
        if (mb_strlen($body ?? '') > config('whatsapp_message_templates.rendered_body_max_length')) {
            throw ValidationException::withMessages(['body' => 'Rendered body is too long.']);
        }
        if (mb_strlen($caption ?? '') > config('whatsapp_message_templates.rendered_caption_max_length')) {
            throw ValidationException::withMessages(['caption' => 'Rendered caption is too long.']);
        }
        $contentHash = $validation->contentHash;
        if ($version->content_hash && ! hash_equals($version->content_hash, $contentHash)) {
            throw ValidationException::withMessages(['template' => 'Stored template content hash does not match content.']);
        }
        $renderHash = $this->hasher->hashArray(['template_content_hash' => $contentHash, 'resolved_values' => $resolved, 'body' => $body, 'caption' => $caption, 'renderer_version' => $version->renderer_version]);

        $media = $version->relationLoaded('attachment') ? $version->attachment : null;
        $attachment = $media ? new RenderedWhatsAppTemplateAttachment($media->uuid, $media->mime_type, $media->size_bytes, $media->checksum_sha256, $media->safe_name, $media->media_category) : null;

        return new RenderedWhatsAppTemplatePayload($template->uuid, $version->uuid, $version->version_number, $template->type, $body, $caption, $resolved, $warnings, $contentHash, $renderHash, $now, $attachment);
    }

    private function normalize(string $key, mixed $value, string $timezone): string
    {
        if ($value instanceof DateTimeInterface) {
            $value = CarbonImmutable::instance($value)->setTimezone($timezone)->format(config('whatsapp_message_templates.datetime_format'));
        } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_float($value)) {
            $value = rtrim(rtrim(sprintf('%.14F', $value), '0'), '.');
        } elseif (is_int($value) || is_string($value)) {
            $value = (string) $value;
        } else {
            throw ValidationException::withMessages(['values.'.$key => 'Variable value must be scalar.']);
        }
        if (! mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) {
            throw ValidationException::withMessages(['values.'.$key => 'Variable value contains invalid characters.']);
        }
        if (mb_strlen($value) > config('whatsapp_message_templates.value_max_length')) {
            throw ValidationException::withMessages(['values.'.$key => 'Variable value is too long.']);
        }
        if ($key === 'email' && $value !== '' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['values.email' => 'Email value is invalid.']);
        }
        if ($key === 'phone' && $value !== '' && ! preg_match('/\A\+[1-9][0-9]{6,14}\z/D', $value)) {
            throw ValidationException::withMessages(['values.phone' => 'Phone value must be normalized international format.']);
        }

        return $value;
    }

    private function substitute(?string $text, array $values): ?string
    {
        if ($text === null) {
            return null;
        } foreach ($values as $key => $value) {
            $text = str_replace('{{'.$key.'}}', $value, $text);
        }

        return $text;
    }
}
