<x-layouts.app :title="$session->name">
    <div
        x-data="{
            state: @js($session->status->value),
            label: @js($session->status->label()),
            qrBase: @js(route('tenant.whatsapp.qr', $session->uuid)),
            qrUrl: @js($session->status->value === 'qr_pending' ? route('tenant.whatsapp.qr', $session->uuid).'?v='.$session->qr_generation_count : ''),
            phone: @js($session->phone_number),
            displayName: @js($session->display_name),
            lastSeen: @js($session->last_seen_at?->diffForHumans()),
            failureMessage: @js($session->failure_message),
            reconnectAttempts: {{ (int) $session->reconnect_attempts }},
            timer: null,
            async refreshStatus() {
                try {
                    const response = await fetch(@js(route('tenant.whatsapp.status', $session->uuid)), {headers:{Accept:'application/json'}});
                    if (!response.ok) return;
                    const data = await response.json();
                    this.state = data.status;
                    this.label = data.status_label;
                    this.phone = data.phone_number;
                    this.displayName = data.display_name;
                    this.failureMessage = data.failure_message;
                    this.reconnectAttempts = data.reconnect_attempts ?? 0;
                    if (data.qr_available) {
                        const next = this.qrBase + '?v=' + data.qr_generation_count;
                        if (this.qrUrl !== next) this.qrUrl = next;
                    } else if (this.state === 'qr_pending') {
                        this.qrUrl = '';
                    }
                    if (data.last_seen_at) this.lastSeen = new Date(data.last_seen_at).toLocaleString();
                    this.schedule(data.next_poll_after_ms);
                } catch (_) {
                    this.schedule(5000);
                }
            },
            schedule(delay) {
                clearTimeout(this.timer);
                if (delay) this.timer = setTimeout(() => { if (!document.hidden) this.refreshStatus(); else this.schedule(delay) }, delay);
            },
            init() { this.schedule(1000); }
        }"
        x-init="init()"
    >
        <x-page-header eyebrow="Linked device" :title="$session->name" description="WhatsApp account status, authentication and connection history.">
            <x-slot:actions>
                <a class="btn-secondary" href="{{ route('tenant.whatsapp.index') }}">All sessions</a>
            </x-slot:actions>
        </x-page-header>

        @if(session('status'))
            <x-alert variant="success" class="mt-6">{{ session('status') }}</x-alert>
        @endif

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-6">
                <section class="panel" aria-live="polite">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                        <div>
                            <p class="eyebrow">Connection state</p>
                            <h2 class="mt-2 text-2xl font-semibold" x-text="label">{{ $session->status->label() }}</h2>
                            <p class="muted mt-2">The connector updates this state automatically.</p>
                        </div>
                        <div
                            class="rounded-full px-3 py-1 text-sm font-semibold"
                            :class="state === 'ready' ? 'bg-emerald-100 text-emerald-700' : (['failed','disconnected'].includes(state) ? 'bg-rose-100 text-rose-700' : 'bg-sky-100 text-sky-700')"
                            x-text="label"
                        ></div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="app-card-subtle p-4">
                            <p class="muted">Account</p>
                            <p class="mt-1 font-semibold" x-text="displayName || 'Awaiting identity'"></p>
                        </div>
                        <div class="app-card-subtle p-4">
                            <p class="muted">Phone</p>
                            <p class="mt-1 font-semibold" x-text="phone || '—'"></p>
                        </div>
                        <div class="app-card-subtle p-4">
                            <p class="muted">Last seen</p>
                            <p class="mt-1 font-semibold" x-text="lastSeen || '—'"></p>
                        </div>
                        <div class="app-card-subtle p-4">
                            <p class="muted">Reconnect attempts</p>
                            <p class="mt-1 font-semibold" x-text="reconnectAttempts"></p>
                        </div>
                    </div>

                    <x-alert variant="danger" class="mt-5" x-cloak x-show="state === 'failed'">
                        <span x-text="failureMessage || 'The connector could not initialize this session. Retry the session or contact support.'"></span>
                    </x-alert>
                </section>

                <section class="panel" x-cloak x-show="state === 'qr_pending'">
                    <div class="grid items-center gap-7 md:grid-cols-[280px_1fr]">
                        <div class="flex aspect-square items-center justify-center rounded-2xl border bg-white p-4 shadow-sm">
                            <img x-show="qrUrl" :src="qrUrl" alt="WhatsApp linked-device QR code" class="aspect-square w-full" width="248" height="248">
                            <div x-show="!qrUrl" class="px-5 text-center text-sm text-slate-600">
                                <span class="mx-auto mb-3 block size-6 animate-spin rounded-full border-2 border-slate-400 border-r-transparent"></span>
                                Waiting for a fresh QR code…
                            </div>
                        </div>
                        <div>
                            <p class="eyebrow">Scan with your phone</p>
                            <h2 class="section-title mt-2">Link this device</h2>
                            <ol class="mt-4 space-y-3 text-sm text-[var(--text-secondary)]">
                                <li><b>1.</b> Open WhatsApp on your phone.</li>
                                <li><b>2.</b> Open Settings or Menu → Linked Devices.</li>
                                <li><b>3.</b> Choose Link a Device and scan this QR code.</li>
                                <li><b>4.</b> Keep this page open until the status changes to Connected.</li>
                            </ol>
                            <p class="muted mt-5">QR codes are short-lived and refresh automatically. Never send a QR screenshot to another person.</p>
                        </div>
                    </div>
                </section>

                <section class="panel" x-cloak x-show="['creating','initializing','authenticating','authenticated','reconnecting'].includes(state)">
                    <div class="flex items-center gap-4">
                        <span class="size-6 animate-spin rounded-full border-2 border-[var(--accent)] border-r-transparent" aria-hidden="true"></span>
                        <div>
                            <h2 class="section-title">Connection in progress</h2>
                            <p class="muted">The isolated session worker is starting or reconnecting. Existing linked-device credentials are reused when available.</p>
                        </div>
                    </div>
                </section>

                <section class="panel" x-cloak x-show="state === 'disconnected'">
                    <x-alert variant="info">This session is paused. Its local linked-device credentials are preserved, so reconnecting normally does not require another QR scan.</x-alert>
                </section>

                <section class="panel">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="eyebrow">Audit trail</p>
                            <h2 class="section-title mt-1">Connection history</h2>
                        </div>
                        <span class="muted text-sm">{{ $session->events->count() }} recent events</span>
                    </div>
                    <div class="mt-5 space-y-4">
                        @forelse($session->events as $event)
                            <article class="border-l-2 border-[var(--accent-soft)] pl-4">
                                <div class="flex justify-between gap-3">
                                    <b class="text-sm">{{ str($event->event)->replace('.',' ')->headline() }}</b>
                                    <time class="text-xs text-[var(--text-muted)]">{{ $event->occurred_at->diffForHumans() }}</time>
                                </div>
                                <p class="muted">{{ $event->message ?: str($event->to_status)->replace('_',' ')->headline() }}</p>
                            </article>
                        @empty
                            <p class="muted">Lifecycle events will appear here.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="panel">
                    <p class="eyebrow">Controls</p>
                    <h2 class="section-title mt-1">Session actions</h2>
                    <div class="mt-4 space-y-3">
                        @if(in_array($session->status->value, ['ready','disconnected','failed']))
                            <form method="post" action="{{ route('tenant.whatsapp.reconnect', $session->uuid) }}">
                                @csrf
                                <button class="btn-primary w-full">{{ $session->status->value === 'ready' ? 'Restart session' : 'Reconnect session' }}</button>
                            </form>
                        @endif

                        @if($session->status->value === 'ready')
                            <form method="post" action="{{ route('tenant.whatsapp.disconnect', $session->uuid) }}" onsubmit="return confirm('Pause this session? Authentication will be preserved for reconnect.')">
                                @csrf
                                <button class="btn-secondary w-full">Pause session safely</button>
                            </form>
                        @endif

                        <form method="post" action="{{ route('tenant.whatsapp.destroy', $session->uuid) }}" onsubmit="return confirm('Permanently remove this WhatsApp session and its connector authentication data? A new QR scan will be required if you add it again.')">
                            @csrf
                            @method('delete')
                            <button class="btn-danger w-full">Remove WhatsApp account</button>
                        </form>
                    </div>
                </section>

                <section class="app-card-subtle p-5">
                    <p class="eyebrow">What each action does</p>
                    <dl class="mt-3 space-y-4 text-sm">
                        <div><dt class="font-semibold">Restart / reconnect</dt><dd class="muted mt-1">Restarts the worker and reuses saved linked-device credentials.</dd></div>
                        <div><dt class="font-semibold">Pause safely</dt><dd class="muted mt-1">Stops the current browser worker without logging the WhatsApp account out.</dd></div>
                        <div><dt class="font-semibold">Remove account</dt><dd class="muted mt-1">Deletes the session and its stored connector authentication data.</dd></div>
                    </dl>
                </section>

                <section class="app-card-subtle p-5">
                    <p class="eyebrow">Responsible use</p>
                    <p class="muted mt-2">WhatsApp Web behavior can change without notice. Use only authorized accounts, honor opt-outs and avoid unsolicited bulk messaging.</p>
                </section>
            </aside>
        </div>
    </div>
</x-layouts.app>
