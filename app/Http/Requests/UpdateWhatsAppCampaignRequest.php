<?php

namespace App\Http\Requests;

final class UpdateWhatsAppCampaignRequest extends StoreWhatsAppCampaignRequest
{
    public function rules(): array
    {
        return [...parent::rules(), 'expected_version' => ['required', 'integer', 'min:1']];
    }
}
