<x-app-layout :title="$campaign->name">
    <div x-data="campaignStatus(@js(route('tenant.campaigns.status',$campaign)), @js($statusPresentation['terminal']))" x-init="start()">
        <x-page-header :title="$campaign->name" :description="$statusPresentation['description']">
            <x-badge :variant="$statusPresentation['severity']">{{ $statusPresentation['label'] }}</x-badge>
            <a class="btn-secondary" href="{{ route('tenant.campaigns.index') }}">Back</a>
        </x-page-header>
        @if(session('success'))<x-alert variant="success">{{ session('success') }}</x-alert>@endif
        @if(session('error'))<x-alert variant="danger">{{ session('error') }}</x-alert>@endif
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach(['Prepared'=>$campaign->eligible_recipient_count,'Sent'=>$campaign->sent_recipient_count,'Delivered'=>$campaign->delivered_recipient_count,'Read'=>$campaign->read_recipient_count,'Failed'=>$campaign->failed_recipient_count,'Progress'=>$campaign->progress_percentage.'%'] as $label=>$value)
                <div class="card p-4"><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold">{{ $value }}</p></div>
            @endforeach
        </div>
        <nav class="mb-5 flex gap-2 overflow-x-auto pb-2" aria-label="Campaign sections">
            <a class="btn-secondary shrink-0" aria-current="page" href="{{ route('tenant.campaigns.show',$campaign) }}">Overview</a>
            <a class="btn-secondary shrink-0" href="#preparation">Preparation</a>
            <a class="btn-secondary shrink-0" href="{{ route('tenant.campaigns.recipients',$campaign) }}">Recipients</a>
            <a class="btn-secondary shrink-0" href="{{ route('tenant.campaigns.exclusions',$campaign) }}">Exclusions</a>
            <a class="btn-secondary shrink-0" href="{{ route('tenant.campaigns.execution',$campaign) }}">Execution</a>
            <a class="btn-secondary shrink-0" href="{{ route('tenant.campaigns.execution.attempts',$campaign) }}">Attempts</a>
            <a class="btn-secondary shrink-0" href="#timeline">Timeline</a>
        </nav>
        <div class="grid gap-5 lg:grid-cols-3">
            <section class="card lg:col-span-2"><h2 class="font-semibold">Message preview</h2><div class="mt-4 max-w-xl rounded-2xl rounded-tr-sm bg-emerald-950 p-4"><p class="text-xs text-emerald-300">{{ strtoupper($campaign->message_type->value) }}</p><p class="mt-2 whitespace-pre-wrap text-slate-200">{{ $campaign->body ?: 'No text content.' }}</p>@if($campaign->attachment)<p class="mt-3 text-sm text-slate-400">{{ $campaign->attachment->safe_name }} · {{ number_format($campaign->attachment->size_bytes/1024,1) }} KB</p>@endif</div></section>
            <section class="card space-y-3 text-sm"><div><span class="text-slate-500">Audience</span><p>{{ str($campaign->audience_type->value)->replace('_',' ')->headline() }}</p></div><div><span class="text-slate-500">Sessions</span><p>{{ str($campaign->session_strategy->value)->replace('_',' ')->headline() }}</p></div><div><span class="text-slate-500">Schedule</span><p>{{ $campaign->scheduled_at_utc?->format('M j, Y H:i').' UTC' ?? 'Manual launch' }}</p></div><div><span class="text-slate-500">Payload version</span><p>v{{ $campaign->version }} · {{ substr($campaign->payload_hash,0,12) }}…</p></div></section>
        </div>
        <div class="my-5 flex flex-wrap gap-2">
            @if(in_array('edit',$statusPresentation['actions']))<a class="btn-secondary" href="{{ route('tenant.campaigns.edit',$campaign) }}">Edit</a>@endif
            @if(in_array('validate',$statusPresentation['actions']))<form method="post" action="{{ route('tenant.campaigns.validate',$campaign) }}">@csrf<input type="hidden" name="expected_version" value="{{ $campaign->version }}"><button class="btn-primary">Validate & mark ready</button></form>@endif
            @if(in_array('schedule',$statusPresentation['actions']))<form method="post" action="{{ route('tenant.campaigns.schedule',$campaign) }}">@csrf<input type="hidden" name="expected_version" value="{{ $campaign->version }}"><input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}"><button class="btn-secondary">Schedule</button></form>@endif
            @if(in_array('unschedule',$statusPresentation['actions']))<form method="post" action="{{ route('tenant.campaigns.unschedule',$campaign) }}">@csrf<input type="hidden" name="expected_version" value="{{ $campaign->version }}"><input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}"><button class="btn-secondary">Unschedule</button></form>@endif
            @if(in_array('duplicate',$statusPresentation['actions']))<form method="post" action="{{ route('tenant.campaigns.duplicate',$campaign) }}" onsubmit="return confirm('Create a new draft without recipients, executions, attempts, or delivery counters?')">@csrf<button class="btn-secondary">Duplicate</button></form>@endif
        </div>
        <div id="preparation">@include('tenant.campaigns._preparation')</div>
        @include('tenant.campaigns._execution')
        <section id="timeline" class="card mt-5"><h2 class="font-semibold">Timeline</h2><div class="mt-4 space-y-4">@forelse($campaign->events->take(50) as $event)<div class="border-l-2 border-emerald-500 pl-4"><p>{{ str($event->event)->replace('.',' ')->headline() }}</p><p class="text-xs text-slate-500">{{ $event->occurred_at->format('M j, Y H:i:s') }}</p></div>@empty<p class="text-slate-400">No campaign events yet.</p>@endforelse</div></section>
        <x-alert x-show="warning" class="mt-5" variant="warning"><span x-text="warning"></span></x-alert>
    </div>
    <script>
        function campaignStatus(url, terminal) {
            return { warning:null, delay:8000, timer:null, start(){ if(!terminal) this.poll() }, async poll(){ try { const response=await fetch(url,{headers:{Accept:'application/json'}}); if(!response.ok) throw new Error(); const data=await response.json(); this.warning=data.warning; this.delay=8000; if(!data.terminal) this.timer=setTimeout(()=>this.poll(),this.delay) } catch(e) { this.delay=Math.min(this.delay*2,60000); this.timer=setTimeout(()=>this.poll(),this.delay) } } }
        }
    </script>
</x-app-layout>
