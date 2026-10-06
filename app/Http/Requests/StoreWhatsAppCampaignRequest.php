<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWhatsAppCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.config('whatsapp_campaigns.name_max')], 'description' => ['nullable', 'string', 'max:'.config('whatsapp_campaigns.description_max')],
            'message_type' => ['required', Rule::in(['text', 'image', 'document', 'audio', 'video'])], 'body' => ['nullable', 'string', 'max:'.config('whatsapp_campaigns.body_max')],
            'attachment' => ['nullable', 'file', 'max:'.config('whatsapp_campaigns.attachment_max_kb'), 'mimetypes:image/jpeg,image/png,image/webp,application/pdf,text/plain,audio/mpeg,audio/ogg,video/mp4'],
            'audience_type' => ['required', Rule::in(['all_eligible_contacts', 'segment', 'groups', 'labels', 'manual_contacts'])],
            'audience_config' => ['array'], 'audience_config.*' => ['uuid'], 'session_strategy' => ['required', Rule::in(['single', 'selected_pool', 'automatic_pool'])],
            'session_uuids' => ['array', 'max:'.config('whatsapp_campaigns.max_selected_sessions')], 'session_uuids.*' => ['uuid', 'distinct'],
            'schedule_type' => ['required', Rule::in(['send_now', 'scheduled'])], 'scheduled_at_local' => ['nullable', 'date'], 'timezone' => ['nullable', 'timezone'],
            'send_window' => ['array'], 'execution' => ['array'], 'idempotency_key' => ['required', 'string', 'max:80'],
            'tenant_id' => ['prohibited'], 'status' => ['prohibited'], 'payload_hash' => ['prohibited'], 'created_by' => ['prohibited'], 'metadata' => ['prohibited'],
        ];
    }
}
