<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Enums\ActiveTenantResolutionStatus;
use App\Enums\MembershipStatus;
use App\Services\ActiveTenantManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenantContext
{
    public function __construct(
        private readonly ActiveTenantManager $manager,
        private readonly TenantContext $context,
        private readonly TenantAuthorization $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);
        $resolution = $this->manager->resolve($user);
        if ($resolution->status === ActiveTenantResolutionStatus::SelectionRequired) {
            return $request->expectsJson()
                ? new JsonResponse(['success' => false, 'error' => ['code' => 'WORKSPACE_SELECTION_REQUIRED', 'message' => 'Select a workspace to continue.']], 409)
                : new RedirectResponse(route('tenant.workspaces.index'));
        }
        $tenant = $resolution->tenant;

        if (! $tenant) {
            $hasAnyMembership = $user->tenantMemberships()->whereNot('status', MembershipStatus::Removed->value)->exists();
            $hasActiveMembership = $user->tenantMemberships()->where('status', MembershipStatus::Active->value)->exists();

            return $this->unavailable($request, $hasAnyMembership, $hasActiveMembership);
        }

        $this->context->set($tenant);
        view()->share('activeTenant', $tenant);
        view()->share('tenantPermissions', $this->authorization->permissions());
        view()->share('currentTenantMembership', $this->authorization->membership()->loadMissing('role'));

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }

    private function unavailable(Request $request, bool $hasMembership, bool $hasActiveMembership): Response
    {
        $code = match (true) {
            ! $hasMembership => 'NO_ACTIVE_TENANT',
            $hasActiveMembership => 'TENANT_SUSPENDED',
            default => 'MEMBERSHIP_INACTIVE',
        };
        $status = $hasActiveMembership ? 423 : 403;
        if ($request->expectsJson()) {
            return new JsonResponse(['success' => false, 'error' => ['code' => $code, 'message' => 'No accessible workspace is available.']], $status);
        }

        return $hasMembership
            ? new RedirectResponse(route('tenant.access.restricted'))
            : new RedirectResponse(route('tenant.no-workspace'));
    }
}
