<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PrepareWhatsAppCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['idempotency_token' => ['required', 'string', 'max:80'], 'expected_version' => ['required', 'integer', 'min:1'], 'tenant_id' => ['prohibited'], 'campaign_id' => ['prohibited'], 'payload_hash' => ['prohibited'], 'phone_numbers' => ['prohibited'], 'contact_ids' => ['prohibited']];
    }
}
