<x-layouts.platform title="Billing">
    <x-page-header eyebrow="Commercial operations" title="Tenant billing" description="Subscriptions, period usage, expiry, and manual payment status." />
    <form method="get" class="panel mt-6 grid gap-4 md:grid-cols-4">
        <label class="text-sm">Plan<select class="field mt-2" name="plan"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->slug }}" @selected($planFilter === $plan->slug)>{{ $plan->name }}</option>@endforeach</select></label>
        <label class="text-sm">Status<select class="field mt-2" name="status"><option value="">All statuses</option>@foreach(['trialing','active','grace','suspended','cancelled','expired'] as $status)<option value="{{ $status }}" @selected($statusFilter === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label>
        <label class="text-sm">Attention<select class="field mt-2" name="scope"><option value="">Any</option><option value="expiring" @selected($scopeFilter === 'expiring')>Expiring in 14 days</option><option value="high_usage" @selected($scopeFilter === 'high_usage')>80%+ message usage</option></select></label>
        <div class="flex items-end gap-2"><button class="btn-primary">Filter</button><a class="btn-secondary" href="{{ route('platform.billing.index') }}">Reset</a></div>
    </form>
    <div class="mt-6 table-shell overflow-x-auto"><table class="data-table"><thead><tr><th>Tenant</th><th>Plan</th><th>Status</th><th>Period end</th><th>Message usage</th><th>Last payment</th><th></th></tr></thead><tbody>@forelse($tenants as $tenant)@php
        $sub = $tenant->currentSubscription;
        $usage = $tenant->billing_usage;
    @endphp<tr><td><b>{{ $tenant->name }}</b></td><td>{{ $sub?->plan?->name ?? 'Unassigned' }}</td><td>{{ $sub ? str($sub->status->value)->headline() : 'Missing' }}</td><td>{{ $sub?->current_period_ends_at?->format('M j, Y') ?? '—' }}</td><td>{{ number_format($usage['usage']) }} / {{ $usage['unlimited'] ? 'Unlimited' : number_format($usage['limit']) }}@if(!$usage['unlimited'])<small class="block text-[var(--text-muted)]">{{ $usage['percentage'] }}%</small>@endif</td><td>{{ $tenant->latestBillingPayment?->paid_at?->format('M j, Y') ?? 'None' }}</td><td><a class="btn-ghost" href="{{ route('platform.tenants.billing.show', $tenant) }}">Manage</a></td></tr>@empty<tr><td colspan="7">No tenants match these filters.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $tenants->links() }}</div>
</x-layouts.platform>
