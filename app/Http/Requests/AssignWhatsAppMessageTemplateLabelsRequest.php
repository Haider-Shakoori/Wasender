<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignWhatsAppMessageTemplateLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['label_uuids' => ['array', 'max:25'], 'label_uuids.*' => ['uuid', 'distinct'], 'category_uuid' => ['nullable', 'uuid']];
    }
}
