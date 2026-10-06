<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tenant;
use App\Services\AccessibleTenantQuery;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class SwitchTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $this->user() !== null
            && $tenant instanceof Tenant
            && app(AccessibleTenantQuery::class)->findForUser($this->user(), $tenant) !== null;
    }

    public function rules(): array
    {
        return [
            'redirect_to' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->isSafeLocalPath((string) $value)) {
                        $fail('The redirect destination is invalid.');
                    }
                },
            ],
        ];
    }

    public function safeRedirect(): ?string
    {
        $value = $this->validated('redirect_to');

        return is_string($value) && $this->isSafeLocalPath($value) ? $value : null;
    }

    private function isSafeLocalPath(string $path): bool
    {
        if (! str_starts_with($path, '/app/') || str_starts_with($path, '//')) {
            return false;
        }
        $scheme = parse_url($path, PHP_URL_SCHEME);
        $host = parse_url($path, PHP_URL_HOST);
        $cleanPath = parse_url($path, PHP_URL_PATH);

        return $scheme === null
            && $host === null
            && ! in_array($cleanPath, ['/app/workspaces'], true)
            && ! str_contains(strtolower($path), 'javascript:');
    }
}
