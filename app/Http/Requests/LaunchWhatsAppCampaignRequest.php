<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LaunchWhatsAppCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['idempotency_token' => ['required', 'string', 'max:80'], 'expected_version' => ['required', 'integer', 'min:1'], 'tenant_id' => ['prohibited'], 'campaign_id' => ['prohibited'], 'execution_id' => ['prohibited'], 'preparation_id' => ['prohibited'], 'status' => ['prohibited'], 'session_id' => ['prohibited'], 'recipient_ids' => ['prohibited'], 'phone_numbers' => ['prohibited'], 'campaign_payload_hash' => ['prohibited'], 'counters' => ['prohibited']];
    }
}
