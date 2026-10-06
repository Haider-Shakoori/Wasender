<x-layouts.platform title="Analytics">
@php
    $metrics = collect($analytics['metrics'])->keyBy('key');
    $value = fn ($key) => $metrics->get($key)?->value ?? 0;
    $series = collect($analytics['message_volume']);
    $seriesMax = max(1, (int) $series->max('value'));
    $preset = $filters['preset'] ?? 'last_30_days';
@endphp
<x-page-header eyebrow="Platform reporting" title="Platform analytics" description="System-wide usage, delivery volume, and operational attention signals." />

<form method="GET" class="panel mt-6 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ preset: '{{ $preset }}' }">
    <label class="text-sm font-medium">Date range<select class="input mt-1 w-full" name="preset" x-model="preset"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="last_7_days">Last 7 days</option><option value="last_30_days">Last 30 days</option><option value="this_month">This month</option><option value="last_month">Last month</option><option value="custom">Custom</option></select></label>
    <label class="text-sm font-medium" x-show="preset === 'custom'">From<input class="input mt-1 w-full" type="date" name="from" value="{{ $filters['from'] ?? '' }}" :required="preset === 'custom'"></label>
    <label class="text-sm font-medium" x-show="preset === 'custom'">To<input class="input mt-1 w-full" type="date" name="to" value="{{ $filters['to'] ?? '' }}" :required="preset === 'custom'"></label>
    <div class="flex items-end"><button class="btn-primary w-full" type="submit">Apply range</button></div>
</form>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
@foreach(['total_tenants'=>'Tenants','active_tenants'=>'Active tenants','connected_sessions'=>'Connected sessions','messages'=>'Messages','failed_messages'=>'Failed messages','campaigns'=>'Campaigns','automation_executions'=>'Automation executions','inbox_conversations'=>'Inbox conversations'] as $key=>$label)
<article class="panel"><p class="muted">{{ $label }}</p><p class="metric-value mt-2">{{ number_format($value($key)) }}</p></article>
@endforeach
</section>

<section class="panel mt-6 overflow-hidden"><div class="border-b pb-4"><h2 class="section-title">Message volume</h2><p class="muted mt-1">Daily outbound traffic across the platform</p></div>
@if($series->isEmpty())<x-empty-state title="No platform message activity" description="Message volume in the selected period will appear here." />
@else<div class="overflow-x-auto pt-5"><div class="flex h-64 min-w-[600px] items-end gap-2" role="img" aria-label="Daily platform message volume">@foreach($series as $point)<div class="flex min-w-7 flex-1 flex-col items-center justify-end gap-2" title="{{ $point->period }}: {{ number_format($point->value) }} messages"><span class="w-3/5 rounded-t bg-[var(--accent)]" style="height: {{ max(2, ($point->value / $seriesMax) * 100) }}%"></span><span class="text-[10px] text-[var(--text-muted)]">{{ \Carbon\Carbon::parse($point->period)->format('M j') }}</span></div>@endforeach</div></div>@endif
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-[.7fr_1.3fr]">
    <section class="panel"><h2 class="section-title">Needs attention</h2><div class="mt-5 space-y-3">@foreach(['unhealthy_sessions'=>'Unhealthy sessions','stuck_workflows'=>'Stuck workflows','failed_campaign_executions'=>'Failed campaign executions','inbox_failures'=>'Inbox failures'] as $key=>$label)<div class="flex items-center justify-between rounded-xl bg-[var(--interactive)] p-3"><span class="text-sm">{{ $label }}</span><span class="rounded-full px-2 py-1 text-sm font-bold {{ $value($key) > 0 ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">{{ number_format($value($key)) }}</span></div>@endforeach</div></section>
    <section class="panel overflow-hidden"><div class="mb-4"><h2 class="section-title">Tenant usage</h2><p class="muted mt-1">Highest recorded monthly message usage</p></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b text-[var(--text-muted)]"><th class="py-3 pr-4">Tenant</th><th class="py-3">Messages used</th></tr></thead><tbody>@forelse($analytics['tenant_usage'] as $tenant)<tr class="border-b last:border-0"><td class="py-3 pr-4 font-medium">{{ $tenant->name }}</td><td class="py-3">{{ number_format($tenant->value) }}</td></tr>@empty<tr><td class="py-8 text-center text-[var(--text-muted)]" colspan="2">No usage snapshots in this period.</td></tr>@endforelse</tbody></table></div></section>
</div>
</x-layouts.platform>
