<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageTeamMembers') ?? false;
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(
                fn ($query) => $query->where('tenant_id', $tenantId)->where('slug', '!=', 'owner')
            )],
            'tenant_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
