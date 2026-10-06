<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', app(TenantContext::class)->get()) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', Rule::in(['USD', 'EUR', 'GBP', 'AED', 'AFN', 'PKR', 'INR'])],
            'locale' => ['required', Rule::in(['en'])],
            'owner_id' => ['prohibited'],
            'status' => ['prohibited'],
            'is_active' => ['prohibited'],
            'tenant_id' => ['prohibited'],
        ];
    }
}
