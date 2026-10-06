<x-layouts.platform title="Users">
 <x-page-header eyebrow="Accounts" title="Users" description="Review identities, workspace access, and platform assignments." />
 <form class="my-6 flex gap-2" role="search"><label class="sr-only" for="q">Search users</label><input id="q" name="q" value="{{ $search }}" class="field max-w-lg" placeholder="Search name or email"><button class="btn-primary">Search</button></form>
 <div class="table-shell overflow-x-auto"><table class="data-table"><thead><tr><th>User</th><th>Workspaces</th><th>Platform role</th><th>Status</th><th></th></tr></thead><tbody>
 @forelse($users as $user)<tr><td><b>{{ $user->name }}</b><div class="text-xs text-[var(--text-muted)]">{{ $user->email }}</div></td><td>{{ $user->tenant_memberships_count }}</td><td>{{ $user->platformRoles->pluck('name')->join(', ') ?: 'None' }}</td><td><x-badge :variant="$user->isActive() ? 'success':'danger'">{{ $user->status->value }}</x-badge></td><td><a class="btn-ghost" href="{{ route('platform.users.show',$user) }}">Inspect</a></td></tr>@empty<tr><td colspan="5">No users found.</td></tr>@endforelse
 </tbody></table></div><div class="mt-5">{{ $users->links() }}</div>
</x-layouts.platform>
