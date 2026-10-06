<x-layouts.platform title="Campaign execution">
    <x-page-header :title="$execution->uuid" description="Redacted connector and execution metadata">
        @if(app(\App\Contracts\PlatformAuthorization::class)->allows(auth()->user(),'platform.campaign_executions.reconcile'))
            <form method="post" action="{{ route('platform.campaign-executions.reconcile',$execution) }}" onsubmit="return confirm('Reconcile canonical counters and stale claims without forcing a send?')">@csrf<button class="btn-primary">Reconcile execution</button></form>
        @endif
    </x-page-header>
    @if(session('status'))<x-alert variant="success">{{ session('status') }}</x-alert>@endif
    <div class="card mt-6 grid gap-4 sm:grid-cols-3">
        <div>Tenant: {{ $execution->tenant->name }}</div>
        <div>Campaign: {{ $execution->campaign->name }}</div>
        <div>Status: {{ $execution->status->value }}</div>
        <div>Total: {{ $execution->total_recipients }}</div>
        <div>Sent: {{ $execution->sent_recipients }}</div>
        <div>Delivered: {{ $execution->delivered_recipients }}</div>
        <div>Read: {{ $execution->read_recipients }}</div>
        <div>Failed: {{ $execution->failed_recipients }}</div>
        <div>Transport pending: {{ $execution->transport_pending_recipients }}</div>
        <div>Unknown: {{ $execution->unknown_recipients }}</div>
        <div>Reservation: {{ $execution->reservation?->status ?? 'missing' }}</div>
        <div>Last connector event: {{ $execution->last_connector_event_at ?? '—' }}</div>
        <div>Payload: {{ hash_equals($execution->campaign_payload_hash,$execution->campaign->payload_hash)?'match':'stale' }}</div>
        <div>Heartbeat: {{ $execution->last_heartbeat_at ?? '—' }}</div>
        <div>Failure: {{ $execution->failure_code ?? '—' }}</div>
    </div>
    <p class="mt-5 text-slate-400">Recipient phones, message content, attachment contents, connector URLs, signatures, and session credentials are hidden.</p>
</x-layouts.platform>
