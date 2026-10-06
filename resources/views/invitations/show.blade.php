<x-layouts.guest title="Workspace invitation">
    <p class="eyebrow">Secure invitation</p><h1 class="mt-2 text-2xl font-bold">Join {{ $invitation->tenant->name }}</h1>
    <p class="mt-4 text-sm leading-6 text-slate-400">{{ $invitation->inviter->name }} invited {{ $invitation->email }} as <strong class="text-slate-200">{{ $invitation->role->name }}</strong>. This link expires {{ $invitation->expires_at->diffForHumans() }}.</p>
    @auth
        @if(mb_strtolower(auth()->user()->email) === $invitation->email)<form class="mt-7" method="post" action="{{ route('invitations.accept',$invitation->uuid) }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="btn-primary w-full">Accept invitation</button></form>
        @else<div class="mt-6 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-300">Sign in as {{ $invitation->email }} to accept this invitation.</div>@endif
    @else
        @if(!$existingUser)<a class="btn-primary mt-7 w-full text-center" href="{{ route('invitations.register',['invitationUuid'=>$invitation->uuid,'token'=>$token]) }}">Create account and join</a>@endif
    @endauth
    <p class="mt-6 text-xs text-slate-500">If you were not expecting this invitation, close this page and ignore the email.</p>
</x-layouts.guest>
