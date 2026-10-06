<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\TenantContext;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTenantInvitationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(
                fn ($query) => $query->where('tenant_id', $tenantId)->where('slug', '!=', 'owner')
            )],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
            'invited_by' => ['prohibited'],
            'expires_at' => ['prohibited'],
        ];
    }
}
