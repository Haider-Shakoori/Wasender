<x-layouts.app title="Overview">
    <x-page-header eyebrow="Workspace overview" title="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}" description="A clear view of access, configuration, and recent workspace activity.">
        <x-slot:actions>@if($tenantPermissions->contains('team.invite'))<a class="btn-primary" href="{{ route('tenant.team.invitations.index') }}">Invite teammate</a>@endif</x-slot:actions>
    </x-page-header>
    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Workspace metrics">
        @foreach([
            ['Team members',$activeMembers,'Active people with workspace access','team'],
            ['Pending invites',$pendingInvitations,'Awaiting acceptance','invite'],
            ['Custom roles',$customRoles,'Tailored permission sets','role'],
            ['Workspace age',((int) $tenant->created_at->diffInDays(now())).'d','Created '.$tenant->created_at->format('M j, Y'),'age'],
        ] as [$label,$value,$detail,$icon])
        <article class="app-card p-5"><div class="flex items-start justify-between"><p class="text-sm font-medium text-[var(--text-secondary)]">{{ $label }}</p><span class="grid size-9 place-items-center rounded-xl bg-[var(--accent-soft)] text-[var(--accent)]" aria-hidden="true">◇</span></div><p class="metric-value mt-5">{{ $value }}</p><p class="mt-1 text-xs text-[var(--text-muted)]">{{ $detail }}</p></article>
        @endforeach
    </section>
    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)]">
        <section class="app-card overflow-hidden"><div class="flex items-center justify-between border-b px-5 py-4 sm:px-6"><div><h2 class="section-title">Recent workspace activity</h2><p class="mt-1 text-xs text-[var(--text-muted)]">Security and configuration events</p></div>@if($tenantPermissions->contains('audit_logs.view'))<a class="text-sm font-semibold text-[var(--accent)] hover:underline" href="{{ route('tenant.audit.index') }}">View all</a>@endif</div>
            @if($tenantPermissions->contains('audit_logs.view') && $recentAuditLogs->isNotEmpty())<div class="divide-y">@foreach($recentAuditLogs as $log)<a class="flex items-start gap-4 px-5 py-4 transition hover:bg-[var(--table-hover)] sm:px-6" href="{{ route('tenant.audit.show',$log->uuid) }}"><span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-[var(--interactive)] text-xs text-[var(--accent)]">◆</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ str($log->action)->replace(['.','_'],' ')->headline() }}</span><span class="mt-0.5 block truncate text-xs text-[var(--text-muted)]">{{ $log->user?->name ?? 'System' }}</span></span><time class="shrink-0 text-xs text-[var(--text-muted)]">{{ $log->created_at->diffForHumans() }}</time></a>@endforeach</div>
            @elseif($tenantPermissions->contains('audit_logs.view'))<x-empty-state title="No activity yet" description="Workspace changes and security events will appear here as your team gets started." />
            @else<x-empty-state title="Activity is protected" description="Your role does not include access to the workspace audit trail." />@endif
        </section>
        <aside class="space-y-6"><section class="app-card p-5 sm:p-6"><div class="flex items-center justify-between"><h2 class="section-title">Workspace health</h2><x-badge variant="success">{{ $tenant->status->label() }}</x-badge></div><dl class="mt-5 space-y-4 text-sm"><div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Your role</dt><dd class="font-medium">{{ $membership->role->name }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Email status</dt><dd class="font-medium">{{ auth()->user()->hasVerifiedEmail() ? 'Verified' : 'Pending' }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Timezone</dt><dd class="truncate font-medium">{{ $tenant->timezone }}</dd></div></dl>@if($tenantPermissions->contains('settings.manage'))<a class="btn-secondary mt-6 w-full" href="{{ route('tenant.settings.edit') }}">Workspace settings</a>@endif</section>
        <section class="app-card-subtle p-5"><p class="eyebrow">Product roadmap</p><h2 class="mt-2 section-title">Communication modules are next</h2><p class="muted mt-2">Sessions, messaging, contacts, and campaigns are clearly marked as unavailable until their product phases are delivered.</p></section></aside>
    </div>
</x-layouts.app>
