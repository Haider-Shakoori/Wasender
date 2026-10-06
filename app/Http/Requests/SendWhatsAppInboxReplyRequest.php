<?php

namespace App\Http\Requests;

use App\Enums\WhatsAppMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SendWhatsAppInboxReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['message_type' => ['required', Rule::enum(WhatsAppMessageType::class)], 'body' => ['nullable', 'string', 'max:'.config('whatsapp_messages.body_max')], 'attachment' => ['nullable', 'file', 'max:'.config('whatsapp_messages.attachment_max_kb')], 'template_uuid' => ['nullable', 'uuid'], 'values' => ['sometimes', 'array', 'max:50'], 'values.*' => ['nullable', 'string', 'max:500'], 'reply_to_message_uuid' => ['nullable', 'uuid'], 'saved_reply_uuid' => ['nullable', 'uuid'], 'idempotency_key' => ['required', 'string', 'min:16', 'max:80']];
    }
}
