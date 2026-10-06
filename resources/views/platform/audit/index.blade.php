<x-layouts.platform title="Audit trail">
 <x-page-header eyebrow="Governance" title="Platform audit trail" description="Append-only administrative events across the platform." />
 <form class="my-6 flex gap-2"><input aria-label="Filter by action" name="action" value="{{ $action }}" class="field max-w-lg" placeholder="Filter by action"><button class="btn-primary">Filter</button></form>
 <div class="table-shell overflow-x-auto"><table class="data-table"><thead><tr><th>Action</th><th>Actor</th><th>Subject</th><th>Time</th></tr></thead><tbody>@forelse($events as $event)<tr><td><b>{{ $event->action }}</b></td><td>{{ $event->actor?->email ?? 'System' }}</td><td>{{ class_basename($event->subject_type ?: 'None') }} {{ $event->subject_id }}</td><td>{{ $event->created_at->format('M j, Y H:i') }}</td></tr>@empty<tr><td colspan="4">No audit events.</td></tr>@endforelse</tbody></table></div><div class="mt-5">{{ $events->links() }}</div>
</x-layouts.platform>
