<x-layouts.app title="Campaigns">
    <x-page-header title="Campaigns" description="Consent-aware WhatsApp campaigns and delivery progress.">
        @can('create', \App\Models\WhatsAppCampaign::class)<a class="btn-primary" href="{{ route('tenant.campaigns.create') }}">New campaign</a>@endcan
    </x-page-header>
    <form class="card mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6" aria-label="Campaign filters">
        <label class="lg:col-span-2"><span class="sr-only">Search</span><input class="input" name="search" value="{{ request('search') }}" placeholder="Search campaigns"></label>
        <label><span class="sr-only">Status</span><select class="input" name="status"><option value="">All statuses</option>@foreach(\App\Enums\WhatsAppCampaignStatus::cases() as $s)<option value="{{ $s->value }}" @selected(request('status')===$s->value)>{{ str($s->value)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
        <label><span class="sr-only">Audience</span><select class="input" name="audience_type"><option value="">All audiences</option>@foreach(\App\Enums\WhatsAppCampaignAudienceType::cases() as $type)<option value="{{ $type->value }}" @selected(request('audience_type')===$type->value)>{{ str($type->value)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
        <label><span class="sr-only">Schedule</span><select class="input" name="schedule_type"><option value="">Any schedule</option><option value="send_now" @selected(request('schedule_type')==='send_now')>Manual launch</option><option value="scheduled" @selected(request('schedule_type')==='scheduled')>Scheduled</option></select></label>
        <button class="btn-secondary">Filter</button>
        <label><span class="text-xs text-slate-500">Updated from</span><input class="input mt-1" type="date" name="date_from" value="{{ request('date_from') }}"></label>
        <label><span class="text-xs text-slate-500">Updated to</span><input class="input mt-1" type="date" name="date_to" value="{{ request('date_to') }}"></label>
        <label class="flex items-center gap-2"><input type="checkbox" name="needs_attention" value="1" @checked(request('needs_attention'))> Needs attention</label>
    </form>
    <div class="table-shell overflow-x-auto">
        <table class="data-table min-w-[1050px]">
            <thead><tr><th>Name</th><th>Status</th><th>Audience</th><th>Recipients</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Failed</th><th>Schedule</th><th>Updated</th><th>Actions</th></tr></thead>
            <tbody>@forelse($campaigns as $c)<tr>
                <td><a class="font-semibold text-emerald-400" href="{{ route('tenant.campaigns.show',$c) }}">{{ $c->name }}</a></td>
                <td><x-badge :variant="in_array($c->status->value,['failed','needs_attention','completed_with_errors'])?'danger':(in_array($c->status->value,['completed','prepared','ready'])?'success':'neutral')">{{ str($c->status->value)->replace('_',' ')->headline() }}</x-badge></td>
                <td>{{ str($c->audience_type->value)->replace('_',' ')->headline() }}</td><td>{{ $c->eligible_recipient_count }}</td>
                <td>{{ $c->sent_recipient_count }}</td><td>{{ $c->delivered_recipient_count }}</td><td>{{ $c->read_recipient_count }}</td><td>{{ $c->failed_recipient_count }}</td>
                <td>{{ $c->scheduled_at_utc?->format('M j, H:i').' UTC' ?? 'Manual' }}</td><td>{{ $c->updated_at->diffForHumans() }}</td>
                <td><a class="text-emerald-400" href="{{ route('tenant.campaigns.show',$c) }}">Review</a></td>
            </tr>@empty<tr><td colspan="11" class="p-10 text-center text-slate-400">No campaigns match these filters.</td></tr>@endforelse</tbody>
        </table>
    </div>
    <div class="mt-5">{{ $campaigns->links() }}</div>
</x-layouts.app>
