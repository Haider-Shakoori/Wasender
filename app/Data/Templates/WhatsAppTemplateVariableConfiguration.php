<?php

namespace App\Data\Templates;

use App\Enums\TemplateVariableContext;
use App\Services\Templates\WhatsAppTemplateVariableRegistry;

final readonly class WhatsAppTemplateVariableConfiguration
{
    public function __construct(public array $values, public array $errors) {}

    public static function normalize(array $input, TemplateVariableContext $context, WhatsAppTemplateVariableRegistry $registry): self
    {
        $values = [];
        $errors = [];
        if (strlen(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) > config('whatsapp_message_templates.variable_config_max_bytes')) {
            $errors[] = ['code' => 'variable_configuration_too_large'];
        }
        foreach ($input as $key => $definition) {
            if (! is_string($key) || ! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key) || ! ($registered = $registry->find($key))) {
                $errors[] = ['code' => 'unknown_variable', 'variable' => (string) $key];

                continue;
            }
            if (! $registered->supports($context)) {
                $errors[] = ['code' => 'variable_context_unavailable', 'variable' => $key];

                continue;
            }
            if (! is_array($definition) || ! array_key_exists('required', $definition) || ! is_bool($definition['required'])) {
                $errors[] = ['code' => 'invalid_required_flag', 'variable' => $key];

                continue;
            }
            $default = $definition['default'] ?? null;
            if ($default !== null && ! is_scalar($default)) {
                $errors[] = ['code' => 'invalid_default', 'variable' => $key];

                continue;
            }
            if ($default !== null && mb_strlen(self::scalar($default)) > config('whatsapp_message_templates.variable_default_max_length')) {
                $errors[] = ['code' => 'default_too_long', 'variable' => $key];

                continue;
            }
            $values[$key] = ['required' => $definition['required'], 'default' => $default];
        }
        ksort($values, SORT_STRING);

        return new self($values, $errors);
    }

    private static function scalar(string|int|float|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}
