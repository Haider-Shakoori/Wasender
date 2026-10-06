<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ArchiveWhatsAppMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['expected_version' => ['required', 'integer', 'min:1']];
    }
}
