<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWhatsAppMessageTemplateLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80'], 'color' => ['nullable', Rule::in(config('whatsapp_message_templates.label_colors'))], 'tenant_id' => ['prohibited'], 'slug' => ['prohibited']];
    }
}
