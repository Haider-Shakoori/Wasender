<x-layouts.platform title="Operations">
    <x-page-header eyebrow="Production readiness" title="Operations" description="Safe, read-only workload and dependency visibility." />
    <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">@foreach($health as $name=>$check)<article class="panel"><div class="flex items-start justify-between gap-3"><div><p class="muted">{{ str($name)->headline() }}</p><p class="mt-2 font-semibold">{{ $check['message'] }}</p></div><x-badge :variant="$check['healthy']?'success':'danger'">{{ $check['healthy']?'Healthy':'Attention' }}</x-badge></div></article>@endforeach</section>
    <section class="mt-8"><div class="flex items-end justify-between gap-4"><div><p class="eyebrow">Workers</p><h2 class="section-title mt-1">Queue backlog</h2></div><a class="btn-secondary" href="{{ route('platform.queues.index') }}">Failed jobs</a></div><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@foreach($queues as $name=>$count)<article class="app-card-subtle p-4"><p class="text-sm font-semibold">{{ $name }}</p><p class="metric-value mt-2">{{ number_format($count) }}</p></article>@endforeach</div></section>
    <section class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4"><article class="panel"><p class="muted">Failed jobs</p><p class="metric-value mt-2">{{ number_format($failed_jobs) }}</p></article><article class="panel"><p class="muted">Active sessions</p><p class="metric-value mt-2">{{ number_format($active_sessions) }}</p></article><article class="panel"><p class="muted">Unhealthy sessions</p><p class="metric-value mt-2">{{ number_format($unhealthy_sessions) }}</p></article><article class="panel"><p class="muted">Unknown message results</p><p class="metric-value mt-2">{{ number_format($unknown_messages) }}</p></article><article class="panel"><p class="muted">Stuck campaigns</p><p class="metric-value mt-2">{{ number_format($stuck_campaigns) }}</p></article><article class="panel"><p class="muted">Stuck automations</p><p class="metric-value mt-2">{{ number_format($stuck_automations) }}</p></article><article class="panel"><p class="muted">Overdue automation delays</p><p class="metric-value mt-2">{{ number_format($overdue_automations) }}</p></article><article class="panel"><p class="muted">Failed webhooks</p><p class="metric-value mt-2">{{ number_format($failed_webhooks) }}</p></article><article class="panel"><p class="muted">Inbox failures</p><p class="metric-value mt-2">{{ number_format($inbox_failures) }}</p></article></section>
    <section class="panel mt-8">
        <div class="flex items-start justify-between gap-4">
            <div><p class="eyebrow">Incidents</p><h2 class="section-title mt-1">Operational alerts</h2><p class="muted mt-2">Repeated failures are grouped so one incident does not flood the platform.</p></div>
            <x-badge :variant="$open_incidents > 0 ? 'danger' : 'success'">{{ $open_incidents }} open</x-badge>
        </div>
        <div class="mt-5 space-y-3">
            @forelse($recent_incidents as $incident)
                <article class="app-card-subtle p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-semibold">{{ $incident->title }}</p>
                                <x-badge :variant="$incident->severity === 'critical' ? 'danger' : ($incident->severity === 'warning' ? 'warning' : 'neutral')">{{ str($incident->severity)->headline() }}</x-badge>
                                <x-badge :variant="$incident->status === 'open' ? 'danger' : 'success'">{{ str($incident->status)->headline() }}</x-badge>
                            </div>
                            <p class="muted mt-2">{{ $incident->message }}</p>
                        </div>
                        <div class="text-right text-sm muted">
                            <p>{{ number_format($incident->occurrences) }} occurrence{{ $incident->occurrences === 1 ? '' : 's' }}</p>
                            <p class="mt-1">{{ optional($incident->last_seen_at)->diffForHumans() }}</p>
                        </div>
                    </div>
                </article>
            @empty
                <p class="muted">No operational incidents have been recorded.</p>
            @endforelse
        </div>
    </section>
    <section class="panel mt-8"><div class="flex items-start justify-between gap-4"><div><p class="eyebrow">Connector</p><h2 class="section-title mt-1">WhatsApp runtime</h2><p class="muted mt-2">{{ data_get($connector, 'remote.service', 'whatsapp-session-service') }} · {{ data_get($connector, 'remote.status', 'unavailable') }}</p></div><x-badge :variant="data_get($connector,'remote.status')==='ok'?'success':'danger'">{{ data_get($connector,'remote.status','Unavailable') }}</x-badge></div><dl class="mt-5 grid gap-4 sm:grid-cols-3"><div><dt class="muted">Owned sessions</dt><dd class="mt-1 font-semibold">{{ data_get($connector, 'remote.owned_sessions', 0) }}</dd></div><div><dt class="muted">Callback backlog</dt><dd class="mt-1 font-semibold">{{ data_get($connector, 'callback_backlog', 0) }}</dd></div><div><dt class="muted">Unknown campaign attempts</dt><dd class="mt-1 font-semibold">{{ data_get($connector, 'unknown_attempts', 0) }}</dd></div></dl></section>
</x-layouts.platform>
