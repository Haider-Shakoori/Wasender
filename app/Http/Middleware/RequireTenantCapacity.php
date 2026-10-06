<?php

namespace App\Http\Middleware;

use App\Contracts\TenantEntitlements;
use App\Exceptions\TenantLimitExceededException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireTenantCapacity
{
    public function handle(Request $r, Closure $next, string $limit): Response
    {
        try {
            app(TenantEntitlements::class)->requireCapacity($limit);

            return $next($r);
        } catch (TenantLimitExceededException $e) {
            if ($r->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'tenant_limit_exceeded', 'limit' => $limit], 422);
            }

            return back()->withErrors(['limit' => $e->getMessage()]);
        }
    }
}
