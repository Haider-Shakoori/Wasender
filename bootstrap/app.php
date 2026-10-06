<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AuthenticateIntegration;
use App\Http\Middleware\EnsureTenantSubscriptionAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RequirePlatformPermission;
use App\Http\Middleware\RequireTenantCapacity;
use App\Http\Middleware\RequireTenantFeature;
use App\Http\Middleware\RequireTenantPermission;
use App\Http\Middleware\ResolveTenantContext;
use App\Http\Middleware\VerifyWhatsAppConnectorSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(AddSecurityHeaders::class);
        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'tenant' => ResolveTenantContext::class,
            'tenant.permission' => RequireTenantPermission::class,
            'user.active' => EnsureUserIsActive::class,
            'platform.permission' => RequirePlatformPermission::class,
            'tenant.subscription' => EnsureTenantSubscriptionAccess::class,
            'tenant.feature' => RequireTenantFeature::class,
            'tenant.capacity' => RequireTenantCapacity::class,
            'whatsapp.signature' => VerifyWhatsAppConnectorSignature::class,
            'integration.auth' => AuthenticateIntegration::class,
        ]);
        $middleware->validateCsrfTokens(except: ['internal/whatsapp/events', 'internal/whatsapp/inbox-events', 'internal/whatsapp/message-events', 'internal/whatsapp/campaign-events', 'billing/webhooks/stripe']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
