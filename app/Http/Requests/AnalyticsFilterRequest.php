<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['preset' => ['nullable', Rule::in(['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'custom'])], 'from' => ['required_if:preset,custom', 'date'], 'to' => ['required_if:preset,custom', 'date', 'after_or_equal:from'], 'session_uuid' => ['nullable', 'uuid'], 'campaign_uuid' => ['nullable', 'uuid'], 'workflow_uuid' => ['nullable', 'uuid'], 'user_uuid' => ['nullable', 'uuid'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']];
    }
}
