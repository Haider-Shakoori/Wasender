<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Str;

final class TenantSlugService
{
    public function generate(string $companyName): string
    {
        $base = Str::limit(Str::slug($companyName), 90, '');
        if ($base === '') {
            $base = 'workspace-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $suffix = 2;
        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = Str::limit($base, 90 - strlen((string) $suffix) - 1, '').'-'.$suffix++;
        }

        return $slug;
    }
}
