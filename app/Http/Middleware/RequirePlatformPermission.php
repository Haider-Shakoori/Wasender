<?php

namespace App\Http\Middleware;

use App\Contracts\PlatformAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePlatformPermission
{
    public function __construct(private readonly PlatformAuthorization $authorization) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user() && $this->authorization->allows($request->user(), $permission), 403);

        return $next($request);
    }
}
