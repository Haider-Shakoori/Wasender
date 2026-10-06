<x-layouts.platform title="Tenants">
 <x-page-header eyebrow="Oversight" title="Tenants" description="Review workspace ownership, membership, and lifecycle state." />
 <form class="my-6 flex gap-2" role="search"><label class="sr-only" for="q">Search tenants</label><input id="q" name="q" value="{{ $search }}" class="field max-w-lg" placeholder="Search name or slug"><button class="btn-primary">Search</button></form>
 <div class="table-shell overflow-x-auto"><table class="data-table"><thead><tr><th>Tenant</th><th>Owner</th><th>Members</th><th>Status</th><th></th></tr></thead><tbody>
 @forelse($tenants as $tenant)<tr><td><b>{{ $tenant->name }}</b><div class="text-xs text-[var(--text-muted)]">{{ $tenant->slug }}</div></td><td>{{ $tenant->owner->email }}</td><td>{{ $tenant->memberships_count }}</td><td><x-badge :variant="$tenant->is_active ? 'success':'danger'">{{ $tenant->status->label() }}</x-badge></td><td><a class="btn-ghost" href="{{ route('platform.tenants.show',$tenant) }}">Inspect</a></td></tr>@empty<tr><td colspan="5">No tenants found.</td></tr>@endforelse
 </tbody></table></div><div class="mt-5">{{ $tenants->links() }}</div>
</x-layouts.platform>
