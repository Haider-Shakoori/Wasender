<x-layouts.platform title="Overview">
    <x-page-header
        eyebrow="Platform administration"
        title="Operational overview"
        description="Tenants, connector health, queues and incidents across the Wasender platform."
    >
        <x-slot:actions>
            <a class="btn-secondary" href="{{ route('platform.operations') }}">Operations</a>
            <a class="btn-primary" href="{{ route('platform.health') }}">System health</a>
        </x-slot:actions>
    </x-page-header>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['Active tenants',$metrics['activeTenants'],'of '.number_format($metrics['tenants']).' total'],
            ['Ready sessions',$metrics['readySessions'],number_format($metrics['unhealthySessions']).' need attention'],
            ['Messages today',$metrics['messagesToday'],'Across all tenants'],
            ['Queued jobs',$metrics['queued'],number_format($metrics['failed']).' failed jobs'],
            ['Open incidents',$metrics['openIncidents'],'Operational alerts'],
        ] as [$label,$value,$detail])
            <article class="panel">
                <p class="muted">{{ $label }}</p>
                <p class="metric-value mt-2">{{ number_format($value) }}</p>
                <p class="mt-1 text-xs text-[var(--text-muted)]">{{ $detail }}</p>
            </article>
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,.75fr)]">
        <section class="panel">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="eyebrow">Recent incidents</p>
                    <h2 class="section-title mt-1">Operations requiring attention</h2>
                </div>
                <a class="btn-ghost" href="{{ route('platform.operations') }}">View operations</a>
            </div>
            <div class="mt-5 space-y-3">
                @forelse($recentIncidents as $incident)
                    <article class="app-card-subtle p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold">{{ $incident->title }}</p>
                                    <x-badge :variant="$incident->severity === 'critical' ? 'danger' : ($incident->severity === 'warning' ? 'warning' : 'neutral')">{{ str($incident->severity)->headline() }}</x-badge>
                                </div>
                                <p class="muted mt-2">{{ $incident->message }}</p>
                            </div>
                            <div class="text-right text-xs text-[var(--text-muted)]">
                                <p>{{ number_format($incident->occurrences) }} occurrence{{ $incident->occurrences === 1 ? '' : 's' }}</p>
                                <p class="mt-1">{{ $incident->last_seen_at?->diffForHumans() }}</p>
                            </div>
                        </div>
                    </article>
                @empty
                    <x-empty-state title="No incidents" description="No operational incidents have been recorded." />
                @endforelse
            </div>
        </section>

        <aside class="space-y-6">
            <section class="panel">
                <p class="eyebrow">Tenant estate</p>
                <h2 class="section-title mt-1">SaaS overview</h2>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="muted">Total tenants</dt><dd class="font-semibold">{{ number_format($metrics['tenants']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="muted">Suspended tenants</dt><dd class="font-semibold">{{ number_format($metrics['suspendedTenants']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="muted">Registered users</dt><dd class="font-semibold">{{ number_format($metrics['users']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="muted">Unhealthy sessions</dt><dd class="font-semibold">{{ number_format($metrics['unhealthySessions']) }}</dd></div>
                </dl>
                <a class="btn-secondary mt-5 w-full" href="{{ route('platform.tenants.index') }}">Manage tenants</a>
            </section>

            <section class="app-card-subtle p-5">
                <p class="eyebrow">Connector runtime</p>
                <p class="muted mt-2">Session workers, crashes, queue health and callback backlogs are monitored from the Operations center.</p>
                <a class="mt-4 inline-block text-sm font-semibold text-[var(--accent)]" href="{{ route('platform.operations') }}">Open Operations →</a>
            </section>
        </aside>
    </div>

    <section class="panel mt-6">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <p class="eyebrow">Administration</p>
                <h2 class="section-title mt-1">Recent platform activity</h2>
            </div>
            <a class="btn-ghost" href="{{ route('platform.audit.index') }}">View audit trail</a>
        </div>
        <div class="space-y-3">
            @forelse($recentEvents as $event)
                <div class="flex flex-col justify-between gap-1 border-b pb-3 last:border-0 sm:flex-row">
                    <div>
                        <b class="text-sm">{{ str($event->action)->replace('.',' ')->headline() }}</b>
                        <p class="muted">{{ $event->actor?->name ?? 'System' }}</p>
                    </div>
                    <time class="text-xs text-[var(--text-muted)]">{{ $event->created_at->diffForHumans() }}</time>
                </div>
            @empty
                <x-empty-state title="No activity yet" description="Platform events will appear here." />
            @endforelse
        </div>
    </section>
</x-layouts.platform>
