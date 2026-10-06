<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Contracts\TenantContext;
use App\Services\AccessibleTenantQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

final class TenantSwitcher extends Component
{
    public readonly Collection $memberships;

    public function __construct(AccessibleTenantQuery $accessible, TenantContext $context)
    {
        $this->memberships = $accessible->forUser(auth()->user(), $context->id());
    }

    public function render(): View
    {
        return view('components.tenant-switcher');
    }
}
