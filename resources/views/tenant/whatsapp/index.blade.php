<x-layouts.app title="WhatsApp sessions">
 <x-page-header eyebrow="Connections" title="WhatsApp sessions" description="Manage isolated WhatsApp Web linked-device connections for this workspace." />
 <x-alert variant="warning" class="mt-6">This uses the unofficial WhatsApp Web interface, not Meta Cloud API. Connections may require rescanning, and abusive automation may result in account restrictions.</x-alert>
 <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
  <section>
   <div class="grid gap-4 md:grid-cols-2">@forelse($sessions as $session)<a href="{{ route('tenant.whatsapp.show',$session->uuid) }}" class="app-card p-5 transition hover:border-[var(--accent)]"><div class="flex items-start justify-between gap-3"><span class="grid size-11 place-items-center rounded-xl bg-[var(--accent-soft)] font-bold text-[var(--accent)]">WA</span><x-badge :variant="$session->status->value==='ready'?'success':($session->status->value==='failed'?'danger':'info')">{{ $session->status->label() }}</x-badge></div><h2 class="mt-5 text-lg font-semibold">{{ $session->name }}</h2><p class="muted mt-1">{{ $session->display_name ?: 'Awaiting linked-device identity' }}</p><p class="mt-4 text-xs text-[var(--text-muted)]">Updated {{ $session->updated_at->diffForHumans() }}</p></a>@empty<div class="md:col-span-2"><x-empty-state title="No WhatsApp sessions" description="Create a connection, then scan the short-lived QR code from WhatsApp Linked Devices." /></div>@endforelse</div>
   <div class="mt-5">{{ $sessions->links() }}</div>
  </section>
  <aside class="panel h-fit"><p class="eyebrow">New connection</p><h2 class="section-title mt-2">Create a session</h2><p class="muted mt-2">Use a recognizable internal name. No messages will be sent.</p><form method="post" action="{{ route('tenant.whatsapp.store') }}" class="mt-5">@csrf<x-input label="Session name" name="name" placeholder="Customer support phone" required maxlength="100"/><button class="btn-primary mt-4 w-full">Create and generate QR</button></form></aside>
 </div>
</x-layouts.app>
