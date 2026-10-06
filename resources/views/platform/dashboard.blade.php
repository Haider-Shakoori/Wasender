<x-layouts.platform title="Overview">
 <x-page-header eyebrow="Platform administration" title="Operational overview" description="Global SaaS health and recent administrative activity." />
 <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  @foreach(['tenants'=>'Total tenants','activeTenants'=>'Active tenants','suspendedTenants'=>'Suspended tenants','users'=>'Registered users','queued'=>'Queued jobs','failed'=>'Failed jobs'] as $key=>$label)
   <article class="panel"><p class="muted">{{ $label }}</p><p class="metric-value mt-2">{{ number_format($metrics[$key]) }}</p></article>
  @endforeach
 </section>
 <section class="panel mt-6"><div class="mb-4 flex items-center justify-between"><h2 class="section-title">Recent platform activity</h2><a class="btn-ghost" href="{{ route('platform.audit.index') }}">View audit trail</a></div>
  <div class="space-y-3">@forelse($recentEvents as $event)<div class="flex flex-col justify-between gap-1 border-b pb-3 last:border-0 sm:flex-row"><div><b class="text-sm">{{ str($event->action)->replace('.',' ')->headline() }}</b><p class="muted">{{ $event->actor?->name ?? 'System' }}</p></div><time class="text-xs text-[var(--text-muted)]">{{ $event->created_at->diffForHumans() }}</time></div>@empty<x-empty-state title="No activity yet">Platform events will appear here.</x-empty-state>@endforelse</div>
 </section>
</x-layouts.platform>
