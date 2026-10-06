<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlatformUserStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformUserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $users = User::query()->with('platformRoles:id,name')->withCount('tenantMemberships')
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->latest()->paginate(20)->withQueryString();

        return view('platform.users.index', compact('users', 'search'));
    }

    public function show(User $user): View
    {
        $user->load(['platformRoles.permissions', 'tenantMemberships.tenant', 'platformNotes.author:id,name']);

        return view('platform.users.show', compact('user'));
    }

    public function suspend(Request $request, User $user, PlatformUserStatusService $service): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        $service->suspend($user, $request->user(), $data['reason']);

        return back()->with('status', 'User suspended.');
    }

    public function reactivate(Request $request, User $user, PlatformUserStatusService $service): RedirectResponse
    {
        $service->reactivate($user, $request->user());

        return back()->with('status', 'User reactivated.');
    }
}
