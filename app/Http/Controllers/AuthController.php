<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Auth\RegisterTenantOwnerData;
use App\Enums\ActiveTenantResolutionStatus;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\ActiveTenantManager;
use App\Services\AuditService;
use App\Services\RegisterTenantOwner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

final class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request, RegisterTenantOwner $register, ActiveTenantManager $active, AuditService $audit): RedirectResponse
    {
        try {
            $result = $register->execute(new RegisterTenantOwnerData(
                name: (string) $request->string('name'),
                email: (string) $request->string('email'),
                companyName: (string) $request->string('company'),
                password: (string) $request->string('password'),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors(['registration' => 'We could not create your workspace. Please try again.']);
        }
        Auth::login($result->user);
        $request->session()->regenerate();
        $active->setForUser($result->user, $result->tenant);
        $audit->recordDomain('tenant.context_resolved', $result->user, $result->tenant, $result->tenant, [
            'tenant_uuid' => $result->tenant->uuid,
            'membership_status' => $result->membership->status->value,
            'selection_source' => 'registration',
        ]);
        $result->user->sendEmailVerificationNotification();

        return redirect()->route('tenant.onboarding');
    }

    public function login(Request $request, AuditService $audit, ActiveTenantManager $active): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = User::where('email', $credentials['email'])->first();
            $audit->recordDomain('user.login_failed', $user, $user?->lastActiveTenant, $user, ['reason' => 'invalid_credentials']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        if (! $request->user()->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'This account is suspended. Contact support for assistance.']);
        }
        $request->session()->regenerate();
        $resolution = $active->resolve($request->user());
        $tenant = $resolution->tenant;
        $audit->recordDomain('user.logged_in', $request->user(), $tenant, $request->user(), ['selection_source' => $active->source()]);
        if ($tenant && $active->source() !== 'session') {
            $audit->recordDomain('tenant.context_resolved', $request->user(), $tenant, $tenant, [
                'tenant_uuid' => $tenant->uuid,
                'selection_source' => $active->source(),
            ]);
        }

        $destination = match ($resolution->status) {
            ActiveTenantResolutionStatus::Resolved => route('tenant.dashboard'),
            ActiveTenantResolutionStatus::SelectionRequired => route('tenant.workspaces.index'),
            ActiveTenantResolutionStatus::NoAccessibleTenant => route('tenant.no-workspace'),
        };

        return redirect()->intended($destination);
    }

    public function logout(Request $request, AuditService $audit, ActiveTenantManager $active, TenantContext $context): RedirectResponse
    {
        $tenant = $active->resolveForUser($request->user());
        $audit->recordDomain('user.logged_out', $request->user(), $tenant, $request->user());
        $active->clear();
        $context->clear();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
