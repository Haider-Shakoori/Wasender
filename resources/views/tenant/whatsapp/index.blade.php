<x-layouts.app title="WhatsApp sessions">
    <x-page-header eyebrow="Connections" title="WhatsApp sessions" description="Connect and manage WhatsApp linked devices for this workspace." />
    <x-alert variant="warning" class="mt-6">This product uses the unofficial WhatsApp Web linked-device interface, not Meta Cloud API. Use authorized accounts and responsible messaging practices.</x-alert>

    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="panel"><p class="muted">Total sessions</p><p class="metric-value mt-2">{{ number_format($summary['total']) }}</p></article>
        <article class="panel"><p class="muted">Connected</p><p class="metric-value mt-2">{{ number_format($summary['ready']) }}</p></article>
        <article class="panel"><p class="muted">Connecting</p><p class="metric-value mt-2">{{ number_format($summary['connecting']) }}</p></article>
        <article class="panel"><p class="muted">Needs attention</p><p class="metric-value mt-2">{{ number_format($summary['attention']) }}</p></article>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section>
            <div class="grid gap-4 md:grid-cols-2">
                @forelse($sessions as $session)
                    <a href="{{ route('tenant.whatsapp.show', $session->uuid) }}" class="app-card p-5 transition hover:border-[var(--accent)]">
                        <div class="flex items-start justify-between gap-3">
                            <div class="grid size-11 place-items-center rounded-xl bg-[var(--accent-soft)] font-bold text-[var(--accent)]">WA</div>
                            <x-badge :variant="$session->status->value === 'ready' ? 'success' : (in_array($session->status->value, ['failed','disconnected']) ? 'danger' : 'info')">
                                {{ $session->status->label() }}
                            </x-badge>
                        </div>
                        <h2 class="mt-5 text-lg font-semibold">{{ $session->name }}</h2>
                        <p class="muted mt-1">{{ $session->display_name ?: 'Awaiting linked-device identity' }}</p>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="app-card-subtle p-3"><p class="muted">Number</p><p class="mt-1 font-medium">{{ $session->phone_number ?: '—' }}</p></div>
                            <div class="app-card-subtle p-3"><p class="muted">Last seen</p><p class="mt-1 font-medium">{{ $session->last_seen_at?->diffForHumans() ?: '—' }}</p></div>
                        </div>
                        <p class="mt-4 text-xs text-[var(--text-muted)]">Updated {{ $session->updated_at->diffForHumans() }}</p>
                    </a>
                @empty
                    <div class="md:col-span-2">
                        <x-empty-state title="No WhatsApp sessions" description="Create a connection, then scan the short-lived QR code from WhatsApp Linked Devices." />
                    </div>
                @endforelse
            </div>
            <div class="mt-5">{{ $sessions->links() }}</div>
        </section>

        <aside class="panel h-fit">
            <p class="eyebrow">New connection</p>
            <h2 class="section-title mt-2">Add WhatsApp account</h2>
            <p class="muted mt-2">Create a session, scan one QR code, and the linked-device credentials will be kept securely on the connector host for reconnects.</p>
            <form method="post" action="{{ route('tenant.whatsapp.store') }}" class="mt-5">
                @csrf
                <x-input label="Session name" name="name" placeholder="Customer support" required maxlength="100"/>
                <button class="btn-primary mt-4 w-full">Create session</button>
            </form>
        </aside>
    </div>
</x-layouts.app>
