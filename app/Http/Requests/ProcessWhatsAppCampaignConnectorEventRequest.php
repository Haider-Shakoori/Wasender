<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ProcessWhatsAppCampaignConnectorEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:80'],
            'event_type' => ['required', Rule::in([
                'campaign.transport.sent', 'campaign.transport.failed', 'campaign.transport.unknown',
                'campaign.message.delivered', 'campaign.message.read', 'campaign.message.failed',
                'campaign.session.disconnected', 'campaign.session.restricted',
            ])],
            'occurred_at' => ['required', 'date'],
            'tenant_uuid' => ['required', 'uuid'],
            'campaign_uuid' => ['nullable', 'uuid'],
            'execution_uuid' => ['nullable', 'uuid'],
            'recipient_execution_uuid' => ['nullable', 'uuid'],
            'dispatch_attempt_uuid' => ['required', 'uuid'],
            'session_uuid' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'string', 'max:160'],
            'transport_reference' => ['nullable', 'string', 'max:255'],
            'whatsapp_message_id' => ['nullable', 'string', 'max:512'],
            'ack_code' => ['nullable', 'integer', 'between:-1,5'],
            'failure' => ['nullable', 'array:class,code,retryable,message'],
            'failure.class' => ['nullable', 'string', 'max:20'],
            'failure.code' => ['nullable', 'string', 'max:80'],
            'failure.retryable' => ['nullable', 'boolean'],
            'failure.message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
