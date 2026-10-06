<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DuplicateWhatsAppMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:150'], 'prefer_draft' => ['sometimes', 'boolean']];
    }
}
