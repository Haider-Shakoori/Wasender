<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWhatsAppConversationNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000'], 'mention_user_uuids' => ['array', 'max:25'], 'mention_user_uuids.*' => ['uuid', 'distinct']];
    }
}
