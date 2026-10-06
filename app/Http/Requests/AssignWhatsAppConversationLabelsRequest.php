<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AssignWhatsAppConversationLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['labels' => ['array', 'max:20'], 'labels.*.name' => ['nullable', 'string', 'max:80'], 'labels.*.color' => ['nullable', 'string', 'max:24']];
    }
}
