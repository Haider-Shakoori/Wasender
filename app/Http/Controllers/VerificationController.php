<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class VerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('tenant.dashboard') : view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('tenant.dashboard')->with('status', 'Email verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'We could not send the verification email right now. Please try again shortly.');
        }

        return back()->with('status', 'Verification link sent.');
    }
}
