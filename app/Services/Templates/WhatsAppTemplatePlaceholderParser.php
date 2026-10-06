<?php

namespace App\Services\Templates;

use App\Data\Templates\TemplateParseResult;
use App\Enums\TemplateVariableContext;

final class WhatsAppTemplatePlaceholderParser
{
    public function __construct(private WhatsAppTemplateVariableRegistry $registry) {}

    public function parse(?string $body, ?string $caption, TemplateVariableContext $context): TemplateParseResult
    {
        $variables = [];
        $errors = [];
        $canonicalBody = $this->parseText($body, 'body', $context, $variables, $errors);
        $canonicalCaption = $this->parseText($caption, 'caption', $context, $variables, $errors);
        $variables = array_values(array_unique($variables));
        sort($variables, SORT_STRING);

        return new TemplateParseResult($canonicalBody, $canonicalCaption, $variables, $errors);
    }

    private function parseText(?string $text, string $field, TemplateVariableContext $context, array &$variables, array &$errors): ?string
    {
        if ($text === null) {
            return null;
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $errors[] = ['code' => 'invalid_utf8', 'field' => $field];

            return $text;
        }
        if (str_contains($text, '{!!') || str_contains($text, '!!}') || str_contains($text, '{{{') || str_contains($text, '}}}')) {
            $errors[] = ['code' => 'unsupported_expression', 'field' => $field];
        }
        preg_match_all('/\{\{([^{}]*)\}\}/u', $text, $matches);
        foreach ($matches[1] as $raw) {
            $key = trim($raw);
            if (! preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $key)) {
                $errors[] = ['code' => 'unsupported_expression', 'field' => $field, 'placeholder' => $raw];

                continue;
            }
            $definition = $this->registry->find($key);
            if (! $definition) {
                $errors[] = ['code' => 'unknown_variable', 'field' => $field, 'variable' => $key];

                continue;
            }
            if (! $definition->supports($context)) {
                $errors[] = ['code' => 'variable_context_unavailable', 'field' => $field, 'variable' => $key];

                continue;
            }
            $variables[] = $key;
        }
        $withoutComplete = preg_replace('/\{\{[^{}]*\}\}/u', '', $text);
        if (str_contains($withoutComplete, '{{') || str_contains($withoutComplete, '}}')) {
            $errors[] = ['code' => 'malformed_placeholder', 'field' => $field];
        }

        return preg_replace_callback('/\{\{([^{}]*)\}\}/u', fn ($match) => preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', trim($match[1])) ? '{{'.trim($match[1]).'}}' : $match[0], $text);
    }
}
