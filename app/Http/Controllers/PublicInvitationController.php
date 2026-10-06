<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RegisterInvitedUserRequest;
use App\Models\User;
use App\Services\ActiveTenantManager;
use App\Services\RegisterInvitedTenantUser;
use App\Services\TenantInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class PublicInvitationController extends Controller
{
    public function show(string $invitationUuid, Request $request, TenantInvitationService $service): View|RedirectResponse
    {
        $token = $request->string('token')->toString();
        $invitation = $service->resolve($invitationUuid, $token);
        $existingUser = User::query()->where('email', $invitation->email)->exists();
        if ($existingUser && ! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('login');
        }

        return view('invitations.show', compact('invitation', 'token', 'existingUser'));
    }

    public function accept(string $invitationUuid, Request $request, TenantInvitationService $service, ActiveTenantManager $active): RedirectResponse
    {
        $token = $request->validate(['token' => ['required', 'string', 'max:255']])['token'];
        $invitation = $service->resolve($invitationUuid, $token);
        $service->accept($invitation, $request->user(), $token);
        $active->setForUser($request->user(), $invitation->tenant);

        return redirect()->route($request->user()->hasVerifiedEmail() ? 'tenant.dashboard' : 'verification.notice')
            ->with('status', "You joined {$invitation->tenant->name}.");
    }

    public function register(string $invitationUuid, Request $request, TenantInvitationService $service): View|RedirectResponse
    {
        $token = $request->string('token')->toString();
        $invitation = $service->resolve($invitationUuid, $token);
        if (User::query()->where('email', $invitation->email)->exists()) {
            return redirect()->guest(route('invitations.show', ['invitationUuid' => $invitationUuid, 'token' => $token]));
        }

        return view('invitations.register', compact('invitation', 'token'));
    }

    public function registerStore(string $invitationUuid, RegisterInvitedUserRequest $request, TenantInvitationService $service, RegisterInvitedTenantUser $register, ActiveTenantManager $active): RedirectResponse
    {
        $token = $request->string('token')->toString();
        $invitation = $service->resolve($invitationUuid, $token);
        $result = $register->execute($invitation, $token, $request->string('name')->toString(), $request->string('password')->toString());
        Auth::login($result['user']);
        $request->session()->regenerate();
        $active->setForUser($result['user'], $invitation->tenant);
        $result['user']->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('status', "You joined {$invitation->tenant->name}. Verify your email to continue.");
    }
}
