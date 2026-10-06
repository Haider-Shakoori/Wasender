<x-layouts.app :title="$campaign->name.' execution'">
    <x-page-header title="Campaign execution" description="Secure connector delivery and acknowledgement status."/>
    @if(!$execution)
        <x-empty-state title="No execution" description="Launch becomes available after a completed recipient preparation."/>
    @else
        <div class="card mt-6 grid gap-4 sm:grid-cols-4">
            @foreach([
                'Status' => $execution->status->value,
                'Total' => $execution->total_recipients,
                'Sent' => $execution->sent_recipients,
                'Delivered' => $execution->delivered_recipients,
                'Read' => $execution->read_recipients,
                'Failed' => $execution->failed_recipients,
                'Retry scheduled' => $execution->retry_scheduled_recipients,
                'Transport pending' => $execution->transport_pending_recipients,
                'Unknown' => $execution->unknown_recipients,
                'Processing' => $execution->processing_recipients,
                'Skipped' => $execution->skipped_recipients,
                'Progress' => $execution->progress_percentage.'%',
            ] as $label => $value)
                <div><span class="text-slate-500">{{ $label }}</span><p>{{ $value }}</p></div>
            @endforeach
        </div>
        <div class="mt-5 flex gap-2">
            <a class="btn-secondary" href="{{ route('tenant.campaigns.execution.recipients',$campaign) }}">Recipient states</a>
            <a class="btn-secondary" href="{{ route('tenant.campaigns.execution.attempts',$campaign) }}">Attempts</a>
        </div>
        <p class="mt-5 rounded-xl border border-slate-700 p-4 text-slate-300">
            Messages are sent through the connected WhatsApp Web session. Delivery and read acknowledgements may be delayed or unavailable.
            @if($execution->unknown_recipients > 0)
                This execution has an uncertain transport state under reconciliation and will not resend that attempt until its outcome is known.
            @endif
        </p>
    @endif
</x-layouts.app>
