<x-layouts.app title="Billing">
    <x-page-header eyebrow="Account" title="Billing & usage" description="Your current subscription, message allowance, feature limits, and payment history." />

    @if (! $subscription)
        <x-alert variant="danger" class="mt-6">No current subscription is assigned. Your data remains safe; contact your platform administrator.</x-alert>
    @else
        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="panel"><p class="muted">Current plan</p><p class="mt-2 text-xl font-semibold">{{ $subscription->plan->name }}</p></article>
            <article class="panel"><p class="muted">Status</p><div class="mt-2"><x-badge :variant="$subscription->status->permitsAccess() ? 'success' : 'danger'">{{ str($subscription->status->value)->headline() }}</x-badge></div></article>
            <article class="panel"><p class="muted">Current period</p><p class="mt-2 font-semibold">{{ $subscription->current_period_starts_at?->format('M j, Y') ?? 'Not set' }} – {{ $subscription->current_period_ends_at?->format('M j, Y') ?? 'Open ended' }}</p></article>
            <article class="panel"><p class="muted">Renewal / expiry</p><p class="mt-2 font-semibold">{{ ($subscription->trial_ends_at ?? $subscription->current_period_ends_at)?->format('M j, Y') ?? 'No scheduled end' }}</p></article>
        </section>

        @if (! $subscription->status->permitsAccess())
            <x-alert variant="danger" class="mt-5">Workspace modules are restricted for billable actions. Historical workspace data remains available. Contact your platform administrator.</x-alert>
        @elseif ($messageUsage && ! $messageUsage['unlimited'] && $messageUsage['percentage'] >= 100)
            <x-alert variant="danger" class="mt-5">Your current plan limit has been reached. Contact your platform administrator.</x-alert>
        @elseif ($messageUsage && ! $messageUsage['unlimited'] && $messageUsage['percentage'] >= 80)
            <x-alert variant="warning" class="mt-5">You're approaching your current message limit.</x-alert>
        @endif

        @if ($messageUsage)
            <section class="panel mt-8">
                <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Messages</p><h2 class="section-title mt-1">Period usage</h2></div><p class="text-lg font-semibold tabular-nums">{{ number_format($messageUsage['usage']) }} / {{ $messageUsage['unlimited'] ? 'Unlimited' : number_format($messageUsage['limit']) }}</p></div>
                <div class="mt-5 h-3 overflow-hidden rounded-full bg-[var(--interactive)]" role="progressbar" aria-label="Message usage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $messageUsage['percentage'] }}"><div class="h-full rounded-full {{ $messageUsage['percentage'] >= 100 ? 'bg-[var(--danger)]' : ($messageUsage['percentage'] >= 80 ? 'bg-[var(--warning)]' : 'bg-[var(--accent)]') }}" style="width: {{ $messageUsage['unlimited'] ? 0 : $messageUsage['percentage'] }}%"></div></div>
                <p class="muted mt-3">{{ $messageUsage['unlimited'] ? 'Unlimited messages' : number_format($messageUsage['remaining']).' messages remaining · '.$messageUsage['percentage'].'% used' }}</p>
            </section>
        @endif

        <section class="mt-8"><p class="eyebrow">Feature limits</p><h2 class="section-title mt-1">Plan capacity</h2><div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@foreach($limits->whereIn('key', ['whatsapp_sessions.max', 'team_members.max', 'automations.max', 'chatbots.max', 'integrations.max']) as $item)<article class="panel"><div class="flex justify-between gap-3"><h3 class="font-semibold">{{ $item['label'] }}</h3><span class="tabular-nums">{{ number_format($item['usage']) }} / {{ $item['unlimited'] ? 'Unlimited' : number_format($item['limit']) }}</span></div></article>@endforeach</div></section>
    @endif

    <section class="mt-10"><p class="eyebrow">Billing history</p><h2 class="section-title mt-1">Recent payments</h2><div class="mt-4 table-shell overflow-x-auto"><table class="data-table"><thead><tr><th>Paid</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->paid_at?->format('M j, Y') ?? 'Pending' }}</td><td>{{ $payment->currency }} {{ number_format($payment->amount / 100, 2) }}</td><td>{{ ucfirst($payment->payment_method) }}</td><td>{{ $payment->reference ?: '—' }}</td><td>{{ ucfirst($payment->status) }}</td></tr>@empty<tr><td colspan="5">No payments recorded.</td></tr>@endforelse</tbody></table></div></section>
    <p class="muted mt-8">Plan changes are handled by a platform administrator. Online checkout is not available.</p>
</x-layouts.app>
