<x-layouts.app title="Analytics">
@php
    $message = collect($analytics['messages']['metrics'])->keyBy('key');
    $session = collect($analytics['sessions']['metrics'])->keyBy('key');
    $campaign = collect($analytics['campaigns']['metrics'])->keyBy('key');
    $automation = collect($analytics['automations']['metrics'])->keyBy('key');
    $inbox = collect($analytics['inbox']['metrics'])->keyBy('key');
    $usage = collect($analytics['usage']['metrics'])->keyBy('key');
    $outboundSeries = collect($analytics['messages']['over_time'])->keyBy('period');
    $inboundSeries = collect($analytics['messages']['inbound_over_time'])->keyBy('period');
    $periods = $outboundSeries->keys()->merge($inboundSeries->keys())->unique()->sort()->values();
    $activityMax = max(1, (int) $outboundSeries->max('value'), (int) $inboundSeries->max('value'));
    $value = fn ($set, $key) => $set->get($key)?->value ?? 0;
    $percent = fn ($number) => number_format((float) $number, 1).'%';
    $preset = $filters['preset'] ?? 'last_30_days';
@endphp
<x-page-header eyebrow="Workspace reporting" title="Analytics" description="A focused view of messaging performance, operations, and plan usage." />

<form method="GET" class="app-card mt-6 grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-5" x-data="{ preset: '{{ $preset }}' }">
    <label class="text-sm font-medium">Date range<select class="input mt-1 w-full" name="preset" x-model="preset"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="last_7_days">Last 7 days</option><option value="last_30_days">Last 30 days</option><option value="this_month">This month</option><option value="last_month">Last month</option><option value="custom">Custom</option></select></label>
    <label class="text-sm font-medium" x-show="preset === 'custom'">From<input class="input mt-1 w-full" type="date" name="from" value="{{ $filters['from'] ?? '' }}" :required="preset === 'custom'"></label>
    <label class="text-sm font-medium" x-show="preset === 'custom'">To<input class="input mt-1 w-full" type="date" name="to" value="{{ $filters['to'] ?? '' }}" :required="preset === 'custom'"></label>
    <label class="text-sm font-medium">Session<select class="input mt-1 w-full" name="session_uuid"><option value="">All sessions</option>@foreach($sessions as $item)<option value="{{ $item->uuid }}" @selected(($filters['session_uuid'] ?? '') === $item->uuid)>{{ $item->name }}</option>@endforeach</select></label>
    <div class="flex items-end"><button class="btn-primary w-full" type="submit">Apply filters</button></div>
</form>

<section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Analytics overview">
@foreach([
    ['Total messages', $value($message, 'total_messages'), 'Inbound and outbound'],
    ['Delivery rate', $percent($value($message, 'delivery_rate')), number_format($value($message, 'delivered')).' delivered'],
    ['Active campaigns', $value($campaign, 'running'), number_format($value($campaign, 'campaigns')).' total'],
    ['Automation success', $percent($value($automation, 'success_rate')), number_format($value($automation, 'executions')).' executions'],
] as [$label,$metric,$detail])
<article class="app-card p-5"><p class="text-sm text-[var(--text-muted)]">{{ $label }}</p><p class="metric-value mt-3">{{ is_numeric($metric) ? number_format($metric) : $metric }}</p><p class="mt-1 text-xs text-[var(--text-muted)]">{{ $detail }}</p></article>
@endforeach
</section>

<section class="app-card mt-6 overflow-hidden">
    <div class="border-b p-5"><h2 class="section-title">Message activity</h2><p class="muted mt-1">Outbound and inbound daily volume</p></div>
    @if($periods->isEmpty())<x-empty-state title="No message activity" description="Messages in the selected period will appear here." />
    @else<div class="overflow-x-auto p-5"><div class="flex h-64 min-w-[600px] items-end gap-2" role="img" aria-label="Daily outbound and inbound message activity">
        @foreach($periods as $day)<div class="flex min-w-8 flex-1 flex-col items-center justify-end gap-1" title="{{ $day }}: {{ (int) ($outboundSeries->get($day)?->value ?? 0) }} outbound, {{ (int) ($inboundSeries->get($day)?->value ?? 0) }} inbound"><div class="flex h-48 w-full items-end justify-center gap-1"><span class="w-2/5 rounded-t bg-[var(--accent)]" style="height: {{ max(2, ((int) ($outboundSeries->get($day)?->value ?? 0) / $activityMax) * 100) }}%"></span><span class="w-2/5 rounded-t bg-emerald-500" style="height: {{ max(2, ((int) ($inboundSeries->get($day)?->value ?? 0) / $activityMax) * 100) }}%"></span></div><span class="text-[10px] text-[var(--text-muted)]">{{ \Carbon\Carbon::parse($day)->format('M j') }}</span></div>@endforeach
    </div><div class="mt-4 flex gap-5 text-xs"><span><i class="mr-1 inline-block size-2 rounded bg-[var(--accent)]"></i>Outbound</span><span><i class="mr-1 inline-block size-2 rounded bg-emerald-500"></i>Inbound</span></div></div>@endif
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="app-card p-5"><h2 class="section-title">Delivery performance</h2><dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3">@foreach(['queued'=>'Queued','sent'=>'Sent','delivered'=>'Delivered','read'=>'Read','failed'=>'Failed'] as $key=>$label)<div><dt class="muted">{{ $label }}</dt><dd class="mt-1 text-xl font-bold">{{ number_format($value($message,$key)) }}</dd></div>@endforeach<div><dt class="muted">Failure rate</dt><dd class="mt-1 text-xl font-bold">{{ $percent($value($message,'failure_rate')) }}</dd></div></dl></section>
    <section class="app-card p-5"><h2 class="section-title">Sessions</h2><dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">@foreach(['total_sessions'=>'Total','connected'=>'Connected','disconnected'=>'Disconnected','unhealthy'=>'Unhealthy'] as $key=>$label)<div><dt class="muted">{{ $label }}</dt><dd class="mt-1 text-xl font-bold">{{ number_format($value($session,$key)) }}</dd></div>@endforeach</dl></section>
</div>

<section class="app-card mt-6 overflow-hidden"><div class="border-b p-5"><h2 class="section-title">Campaigns</h2><p class="muted mt-1">Highest-volume campaigns in this period</p></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b text-[var(--text-muted)]"><th class="p-4">Campaign</th><th class="p-4">Sent</th><th class="p-4">Delivered</th><th class="p-4">Read</th><th class="p-4">Failed</th></tr></thead><tbody>@forelse($analytics['campaigns']['top'] as $row)<tr class="border-b last:border-0"><td class="p-4 font-medium">{{ $row->name }}</td><td class="p-4">{{ number_format($row->sent_recipient_count) }}</td><td class="p-4">{{ number_format($row->delivered_recipient_count) }}</td><td class="p-4">{{ number_format($row->read_recipient_count) }}</td><td class="p-4">{{ number_format($row->failed_recipient_count) }}</td></tr>@empty<tr><td class="p-6 text-center text-[var(--text-muted)]" colspan="5">No campaigns in this period.</td></tr>@endforelse</tbody></table></div></section>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="app-card p-5"><h2 class="section-title">Automations</h2><dl class="mt-5 space-y-3">@foreach(['enabled'=>'Enabled workflows','executions'=>'Executions','completed'=>'Completed','failed'=>'Failed','waiting'=>'Waiting'] as $key=>$label)<div class="flex justify-between"><dt class="muted">{{ $label }}</dt><dd class="font-semibold">{{ number_format($value($automation,$key)) }}</dd></div>@endforeach</dl></section>
    <section class="app-card p-5"><h2 class="section-title">Shared inbox</h2><dl class="mt-5 space-y-3">@foreach(['open'=>'Open','pending'=>'Pending','unread'=>'Unread','unassigned'=>'Unassigned','urgent'=>'Urgent'] as $key=>$label)<div class="flex justify-between"><dt class="muted">{{ $label }}</dt><dd class="font-semibold">{{ number_format($value($inbox,$key)) }}</dd></div>@endforeach</dl></section>
    <section class="app-card p-5"><h2 class="section-title">Plan usage</h2><p class="mt-2 text-sm font-semibold">{{ $planName }}</p>@php($limit = $value($usage,'message_limit')) @php($used = $value($usage,'message_usage')) @php($unlimited = $analytics['usage']['unlimited_messages'])<div class="mt-5 h-2 overflow-hidden rounded-full bg-[var(--interactive)]"><div class="h-full rounded-full bg-[var(--accent)]" style="width: {{ $unlimited ? 0 : min(100, $limit > 0 ? ($used / $limit) * 100 : 0) }}%"></div></div><p class="muted mt-2">{{ number_format($used) }} messages used · {{ $unlimited ? 'Unlimited' : number_format($value($usage,'message_remaining')).' remaining' }}</p><dl class="mt-4 space-y-3"><div class="flex justify-between"><dt class="muted">Campaign usage</dt><dd class="font-semibold">{{ number_format($value($usage,'campaign_usage')) }}</dd></div><div class="flex justify-between"><dt class="muted">Automation usage</dt><dd class="font-semibold">{{ number_format($value($usage,'automation_usage')) }}</dd></div></dl></section>
</div>
</x-layouts.app>
