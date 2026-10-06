<?php

namespace App\Http\Requests;

use App\Enums\TemplateVariableContext;
use App\Enums\WhatsAppMessageTemplateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWhatsAppMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:1000'], 'type' => ['required', Rule::in(WhatsAppMessageTemplateType::values())], 'body' => ['nullable', 'string', 'max:'.config('whatsapp_message_templates.body_max_length')], 'caption' => ['nullable', 'string', 'max:'.config('whatsapp_message_templates.caption_max_length')], 'variable_context' => ['sometimes', Rule::in(TemplateVariableContext::values())], 'variable_configuration' => ['sometimes', 'array', 'max:'.config('whatsapp_message_templates.max_variables')], 'tenant_id' => ['prohibited'], 'status' => ['prohibited'], 'content_configuration' => ['prohibited']];
    }
}
