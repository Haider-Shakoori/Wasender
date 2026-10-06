<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeWhatsAppConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['sometimes', Rule::in(['open', 'pending', 'closed', 'archived'])], 'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])]];
    }
}
