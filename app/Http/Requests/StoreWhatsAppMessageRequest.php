<?php

namespace App\Http\Requests;

use App\Enums\WhatsAppMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreWhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['session_uuid' => ['required', 'uuid'], 'recipient' => ['required', 'string', 'max:40'],
            'message_type' => ['required', Rule::enum(WhatsAppMessageType::class)],
            'body' => ['nullable', 'string', 'max:'.config('whatsapp_messages.body_max'), 'required_if:message_type,text'],
            'attachment' => ['nullable', 'file', 'max:'.config('whatsapp_messages.attachment_max_kb'), 'required_unless:message_type,text'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80']];
    }
}
