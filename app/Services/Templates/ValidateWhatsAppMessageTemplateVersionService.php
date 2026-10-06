<?php

namespace App\Services\Templates;

use App\Data\Templates\TemplateValidationResult;
use App\Data\Templates\WhatsAppTemplateVariableConfiguration;
use App\Enums\WhatsAppMessageTemplateType;
use App\Models\WhatsAppMessageTemplateVersion;
use Illuminate\Support\Facades\Storage;

final class ValidateWhatsAppMessageTemplateVersionService
{
    public function __construct(private WhatsAppTemplateVariableRegistry $registry, private WhatsAppTemplatePlaceholderParser $parser, private WhatsAppMessageTemplateContentHasher $hasher) {}

    public function validate(WhatsAppMessageTemplateVersion $version, WhatsAppMessageTemplateType $type): TemplateValidationResult
    {
        $errors = [];
        $warnings = [];
        $context = $version->variable_context;
        $parsed = $this->parser->parse($version->body, $version->caption, $context);
        array_push($errors, ...$parsed->errors);
        if (mb_strlen($parsed->body ?? '') > config('whatsapp_message_templates.body_max_length')) {
            $errors[] = ['code' => 'body_too_long'];
        }
        if (mb_strlen($parsed->caption ?? '') > config('whatsapp_message_templates.caption_max_length')) {
            $errors[] = ['code' => 'caption_too_long'];
        }
        if (count($parsed->variables) > config('whatsapp_message_templates.max_variables')) {
            $errors[] = ['code' => 'too_many_variables'];
        }
        if ($version->parser_version !== config('whatsapp_message_templates.parser_version')) {
            $errors[] = ['code' => 'unsupported_parser_version'];
        }
        if ($version->renderer_version !== config('whatsapp_message_templates.renderer_version')) {
            $errors[] = ['code' => 'unsupported_renderer_version'];
        }
        $configuration = WhatsAppTemplateVariableConfiguration::normalize($version->variable_configuration ?? [], $context, $this->registry);
        array_push($errors, ...$configuration->errors);
        foreach ($parsed->variables as $key) {
            if (! isset($configuration->values[$key])) {
                $errors[] = ['code' => 'missing_variable_configuration', 'variable' => $key];
            }
        }
        foreach ($configuration->values as $key => $definition) {
            if (! in_array($key, $parsed->variables, true)) {
                $warnings[] = ['code' => 'configured_variable_unused', 'variable' => $key];
            }
            if (! $definition['required'] && $definition['default'] === null) {
                $warnings[] = ['code' => 'optional_variable_without_default', 'variable' => $key];
            }
        }
        if ($type === WhatsAppMessageTemplateType::Text && blank($parsed->body)) {
            $errors[] = ['code' => 'text_body_required'];
        }
        $attachment = $version->relationLoaded('attachment') ? $version->attachment : null;
        if ($type !== WhatsAppMessageTemplateType::Text) {
            if (! $attachment) {
                $errors[] = ['code' => 'media_attachment_required'];
            } elseif ($attachment->whatsapp_message_template_version_id !== $version->id || $attachment->whatsapp_message_template_id !== $version->whatsapp_message_template_id) {
                $errors[] = ['code' => 'attachment_owner_mismatch'];
            } elseif ($attachment->media_category !== $type->value || ! in_array($attachment->mime_type, config("whatsapp_message_templates.attachment_mimes.{$type->value}", []), true)) {
                $errors[] = ['code' => 'attachment_mime_invalid'];
            } elseif (! $attachment->checksum_sha256 || $attachment->size_bytes < 1) {
                $errors[] = ['code' => 'attachment_metadata_invalid'];
            } elseif ($attachment->size_bytes > config("whatsapp_message_templates.attachment_limits_mb.{$type->value}") * 1024 * 1024) {
                $errors[] = ['code' => 'attachment_too_large'];
            } elseif (! Storage::disk($attachment->disk)->exists($attachment->storage_key)) {
                $errors[] = ['code' => 'attachment_file_missing'];
            } elseif ($attachment->size_bytes > config("whatsapp_message_templates.attachment_limits_mb.{$type->value}") * 1024 * 1024 * .8) {
                $warnings[] = ['code' => 'attachment_near_limit'];
            }
        }
        $hash = $errors === [] ? $this->hasher->hash($version, $type, $parsed->body, $parsed->caption, $configuration->values) : null;

        return new TemplateValidationResult($errors, $warnings, $parsed->variables, $hash);
    }
}
