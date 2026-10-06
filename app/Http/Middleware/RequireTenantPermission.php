<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\TenantAuthorization;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireTenantPermission
{
    public function __construct(private readonly TenantAuthorization $authorization) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if ($this->authorization->denies($permission)) {
            return $request->expectsJson()
                ? new JsonResponse(['success' => false, 'error' => ['code' => 'TENANT_PERMISSION_DENIED', 'message' => 'This action is not authorized.']], 403)
                : abort(403);
        }

        return $next($request);
    }
}
