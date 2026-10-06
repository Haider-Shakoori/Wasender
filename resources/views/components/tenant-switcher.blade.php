@php($current = $memberships->firstWhere('tenant_id', $activeTenant->id))
<div class="relative" x-data="{ open: false }" @keydown.escape.window="open=false" @click.outside="open=false">
    @if($memberships->count() > 1)
        <button type="button" @click="open=!open" :aria-expanded="open" aria-haspopup="menu" class="flex w-full items-center gap-3 rounded-xl px-2.5 py-2 text-left transition hover:bg-white/6 focus-visible:ring-offset-[var(--sidebar)]">
            @if($activeTenant->logo_path)<img class="size-9 rounded-xl object-cover" src="{{ asset('storage/'.$activeTenant->logo_path) }}" alt="">@else<span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white/8 text-sm font-bold text-emerald-300">{{ str($activeTenant->name)->substr(0,1)->upper() }}</span>@endif
            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $activeTenant->name }}</span><span class="block truncate text-xs text-[var(--sidebar-muted)]">{{ $current?->role?->name }}</span></span><span class="text-[var(--sidebar-muted)]" aria-hidden="true">⌄</span>
        </button>
        <div x-cloak x-show="open" x-transition role="menu" class="absolute left-0 top-full z-[70] mt-2 w-[min(272px,calc(100vw-2rem))] rounded-xl border border-[var(--border)] bg-[var(--surface-raised)] p-2 text-[var(--text)] shadow-2xl">
            <p class="px-3 pb-2 pt-1 text-[10px] font-bold uppercase tracking-[.14em] text-[var(--text-muted)]">Switch workspace</p>
            @foreach($memberships as $membership)
                @if($membership->tenant_id === $activeTenant->id)<div class="flex items-center justify-between rounded-lg bg-[var(--accent-soft)] px-3 py-2.5" role="menuitem"><span class="min-w-0"><b class="block truncate text-sm">{{ $membership->tenant->name }}</b><small class="text-[var(--text-muted)]">{{ $membership->role->name }}</small></span><x-badge variant="success">Current</x-badge></div>
                @else<form method="post" action="{{ route('tenant.switch',$membership->tenant) }}" role="none">@csrf<input type="hidden" name="redirect_to" value="/app/dashboard"><button class="flex w-full items-center rounded-lg px-3 py-2.5 text-left hover:bg-[var(--interactive)]" role="menuitem"><span class="min-w-0"><b class="block truncate text-sm">{{ $membership->tenant->name }}</b><small class="text-[var(--text-muted)]">{{ $membership->role->name }}</small></span></button></form>@endif
            @endforeach
            <a class="mt-1 block border-t px-3 py-2.5 text-sm text-[var(--text-secondary)] hover:text-[var(--text)]" href="{{ route('tenant.workspaces.index') }}">View all workspaces</a>
        </div>
    @else
        <div class="flex items-center gap-3 px-2.5 py-2">@if($activeTenant->logo_path)<img class="size-9 rounded-xl object-cover" src="{{ asset('storage/'.$activeTenant->logo_path) }}" alt="">@else<span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white/8 text-sm font-bold text-emerald-300">{{ str($activeTenant->name)->substr(0,1)->upper() }}</span>@endif<span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ $activeTenant->name }}</span><span class="block truncate text-xs text-[var(--sidebar-muted)]">{{ $current?->role?->name }}</span></span></div>
    @endif
</div>
