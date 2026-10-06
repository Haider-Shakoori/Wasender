<?php

namespace App\Http\Requests;

final class PlatformAnalyticsFilterRequest extends AnalyticsFilterRequest
{
    public function rules(): array
    {
        return parent::rules() + ['tenant_uuid' => ['nullable', 'uuid']];
    }
}
