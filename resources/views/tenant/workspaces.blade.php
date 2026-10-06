<x-layouts.guest title="Choose workspace">
    <p class="eyebrow">Workspace access</p>
    <h1 class="mt-2 text-2xl font-bold">Choose a workspace</h1>
    <p class="mt-2 text-sm text-slate-400">Select where you want to continue. Only active memberships are shown.</p>
    <div class="mt-6 space-y-3">
        @foreach($memberships as $membership)
            <form method="post" action="{{ route('tenant.switch', $membership->tenant) }}">
                @csrf
                <button class="flex w-full items-center justify-between rounded-xl border border-slate-700 p-4 text-left hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                    <span class="flex items-center gap-3">@if($membership->tenant->logo_path)<img class="size-10 rounded-lg object-cover" src="{{ asset('storage/'.$membership->tenant->logo_path) }}" alt="">@else<span class="grid size-10 place-items-center rounded-lg bg-emerald-400/10 font-bold text-emerald-300">{{ str($membership->tenant->name)->substr(0, 1)->upper() }}</span>@endif<span><b class="block">{{ $membership->tenant->name }}</b><small class="text-slate-400">{{ $membership->role->name }} · Created {{ $membership->tenant->created_at->format('M Y') }}</small></span></span>
                    <span aria-hidden="true">→</span>
                </button>
            </form>
        @endforeach
    </div>
    <form class="mt-6" method="post" action="{{ route('logout') }}">@csrf<button class="btn-secondary w-full">Sign out</button></form>
</x-layouts.guest>
