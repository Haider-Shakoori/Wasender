<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EstimateWhatsAppCampaignAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['tenant_id' => ['prohibited'], 'phone_numbers' => ['prohibited'], 'contact_ids' => ['prohibited']];
    }
}
