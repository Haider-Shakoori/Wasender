<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewWhatsAppMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['values' => ['sometimes', 'array', 'max:'.config('whatsapp_message_templates.max_variables')], 'values.*' => ['nullable', function ($attribute, $value, $fail): void {
            if (! is_scalar($value) || mb_strlen((string) $value) > config('whatsapp_message_templates.value_max_length')) {
                $fail('Preview values must be bounded scalars.');
            }
        }], 'timezone' => ['sometimes', 'timezone']];
    }
}
