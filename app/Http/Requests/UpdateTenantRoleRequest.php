<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\TenantRoleQuery;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTenantRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = app(TenantRoleQuery::class)->findByUuid((string) $this->route('roleUuid'));

        return $this->user()?->can('update', $role) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
            'tenant_id' => ['prohibited'],
            'is_system' => ['prohibited'],
        ];
    }
}
