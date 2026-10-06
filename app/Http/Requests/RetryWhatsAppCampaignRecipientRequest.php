<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RetryWhatsAppCampaignRecipientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['idempotency_token' => ['required', 'string', 'max:80'], 'tenant_id' => ['prohibited'], 'session_id' => ['prohibited'], 'attempt_count' => ['prohibited'], 'status' => ['prohibited']];
    }
}
