<?php

namespace App\Http\Middleware;

use App\Contracts\TenantEntitlements;
use App\Exceptions\TenantSubscriptionInactiveException;
use App\Exceptions\TenantSubscriptionMissingException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantSubscriptionAccess
{
    public function handle(Request $r, Closure $next): Response
    {
        try {
            app(TenantEntitlements::class)->subscription();

            return $next($r);
        } catch (TenantSubscriptionInactiveException|TenantSubscriptionMissingException $e) {
            if ($r->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'tenant_subscription_inactive'], 423);
            }

            return redirect()->route('tenant.subscription.show');
        }
    }
}
