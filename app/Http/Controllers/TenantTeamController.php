<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Services\TenantTeamQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class TenantTeamController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, TenantTeamQuery $team): View
    {
        $this->authorize('viewAny', TenantMembership::class);
        $roles = Role::forTenant($context->id())->where('slug', '!=', 'owner')->orderBy('name')->get();

        return view('tenant.team.index', [
            'members' => $team->paginate($request->string('search')->toString(), $request->string('status')->toString()),
            'counts' => $team->counts(), 'roles' => $roles,
        ]);
    }
}
