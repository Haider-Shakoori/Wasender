<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendWhatsAppMessageFromTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['session_uuid' => ['required', 'uuid'], 'recipient' => ['required', 'string', 'max:40'], 'template_uuid' => ['required', 'uuid'], 'values' => ['sometimes', 'array', 'max:50'], 'values.*' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_scalar($value) || (is_string($value) && mb_strlen($value) > config('whatsapp_message_templates.value_max_length'))) {
                $fail('Template variable values must be bounded scalar values.');
            }
        }], 'timezone' => ['sometimes', 'timezone:all'], 'idempotency_key' => ['required', 'string', 'min:16', 'max:80']];
    }
}
