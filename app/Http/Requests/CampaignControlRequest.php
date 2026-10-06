<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CampaignControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['idempotency_token' => ['required', 'string', 'max:80'], 'expected_version' => ['required', 'integer', 'min:1'], 'reason' => ['nullable', 'string', 'max:500'], 'tenant_id' => ['prohibited'], 'status' => ['prohibited'], 'session_id' => ['prohibited'], 'recipient_ids' => ['prohibited'], 'phone_numbers' => ['prohibited']];
    }
}
