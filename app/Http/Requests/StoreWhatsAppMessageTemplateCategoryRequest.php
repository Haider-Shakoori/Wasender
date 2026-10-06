<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWhatsAppMessageTemplateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:1000'], 'tenant_id' => ['prohibited'], 'slug' => ['prohibited']];
    }
}
