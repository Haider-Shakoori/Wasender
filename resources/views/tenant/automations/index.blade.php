<x-layouts.app title="Automations">
    <x-page-header eyebrow="Engagement" title="Automations" description="Build and monitor reliable tenant workflows.">
        <x-slot:actions>@can('create', App\Models\AutomationWorkflow::class)<a class="btn-primary" href="{{ route('tenant.automations.create') }}">New workflow</a>@endcan</x-slot:actions>
    </x-page-header>
    <form class="panel mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <input class="input lg:col-span-2" name="search" value="{{ request('search') }}" placeholder="Search workflows">
        <select class="input" name="status"><option value="">All statuses</option>@foreach(App\Enums\AutomationWorkflowStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status')===$status->value)>{{ str($status->value)->headline() }}</option>@endforeach</select>
        <select class="input" name="enabled"><option value="">Enabled or disabled</option><option value="1" @selected(request('enabled')==='1')>Enabled</option><option value="0" @selected(request('enabled')==='0')>Disabled</option></select>
        <button class="btn-secondary">Apply filters</button>
    </form>
    <div class="table-shell overflow-x-auto"><table class="data-table min-w-[940px]"><thead><tr><th>Workflow</th><th>Status</th><th>Enabled</th><th>Trigger</th><th>Last updated</th><th>Last execution</th><th>Actions</th></tr></thead><tbody>
        @forelse($workflows as $workflow)<tr><td><a class="font-medium text-emerald-500" href="{{ route('tenant.automations.show',$workflow) }}">{{ $workflow->name }}</a><p class="max-w-72 truncate text-xs text-[var(--text-muted)]">{{ $workflow->description ?: 'No description' }}</p></td><td><x-badge>{{ str($workflow->status->value)->headline() }}</x-badge></td><td>{{ $workflow->is_enabled ? 'Yes' : 'No' }}</td><td>{{ str(($workflow->currentPublishedVersion ?? $workflow->currentDraftVersion)?->trigger_type?->value ?? 'manual')->headline() }}</td><td>{{ $workflow->updated_at->diffForHumans() }}</td><td>@if($workflow->latestExecution)<a class="text-emerald-500" href="{{ route('tenant.automation-executions.show',$workflow->latestExecution) }}">{{ str($workflow->latestExecution->status->value)->headline() }}</a><p class="text-xs text-[var(--text-muted)]">{{ $workflow->latestExecution->created_at->diffForHumans() }}</p>@else—@endif</td><td class="space-x-2"><a class="btn-secondary" href="{{ route('tenant.automations.show',$workflow) }}">View</a>@if($workflow->current_draft_version_id)@can('update',$workflow)<a class="btn-secondary" href="{{ route('tenant.automations.edit',$workflow) }}">Edit</a>@endcan@endif</td></tr>
        @empty<tr><td colspan="7"><x-empty-state title="No workflows" description="Create a manual workflow to automate repeatable work." /></td></tr>@endforelse
    </tbody></table></div><div class="mt-5">{{ $workflows->links() }}</div>
</x-layouts.app>
