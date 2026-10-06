<?php

namespace App\Http\Middleware;

use App\Contracts\TenantEntitlements;
use App\Exceptions\TenantFeatureUnavailableException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireTenantFeature
{
    public function handle(Request $r, Closure $next, string $feature): Response
    {
        try {
            app(TenantEntitlements::class)->requireFeature($feature);

            return $next($r);
        } catch (TenantFeatureUnavailableException $e) {
            if ($r->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'tenant_feature_unavailable', 'feature' => $feature], 403);
            }abort(403, $e->getMessage());
        }
    }
}
