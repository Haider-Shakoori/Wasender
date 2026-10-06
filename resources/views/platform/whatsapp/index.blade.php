<x-layouts.platform title="WhatsApp sessions">
 <x-page-header eyebrow="Operations" title="WhatsApp sessions" description="Cross-tenant connection health and safe recovery controls. Authentication data and QR codes are never shown here." />
 <form class="mt-6 flex max-w-xl items-end gap-2"><x-input label="Search sessions" name="search" :value="$search" placeholder="Search tenant, name, or exact UUID"/><button class="btn-secondary">Search</button></form>
 <div class="app-card mt-6 overflow-x-auto">
  <table class="data-table"><thead><tr><th>Session</th><th>Tenant</th><th>Status</th><th>Last seen</th><th>Updated</th><th></th></tr></thead>
   <tbody>@forelse($sessions as $session)<tr><td><p class="font-medium">{{ $session->name }}</p><code class="text-xs text-[var(--text-muted)]">{{ $session->uuid }}</code></td><td>{{ $session->tenant->name }}</td><td><x-badge :variant="$session->status->value==='ready'?'success':($session->status->value==='failed'?'danger':'info')">{{ $session->status->label() }}</x-badge></td><td>{{ $session->last_seen_at?->diffForHumans() ?? 'Never' }}</td><td>{{ $session->updated_at->diffForHumans() }}</td><td>@if(in_array($session->status->value,['disconnected','failed']))<form method="post" action="{{ route('platform.whatsapp.reconnect',$session) }}">@csrf<button class="btn-secondary">Queue reconnect</button></form>@endif</td></tr>@empty<tr><td colspan="6"><x-empty-state title="No sessions found" description="Adjust the search or wait for a tenant to create a connection."/></td></tr>@endforelse</tbody>
  </table>
 </div>
 <div class="mt-5">{{ $sessions->links() }}</div>
</x-layouts.platform>
