<x-layouts.app title="Welcome">
    <x-page-header eyebrow="Workspace ready" title="Welcome to {{ $activeTenant->name }}" description="Your hosted WhatsApp workspace is ready. You are subscribing to the service; the platform software remains centrally managed." />

    <div class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel">
            <p class="eyebrow">Get started</p>
            <h2 class="section-title mt-1">Four steps to your first connected account</h2>
            <ol class="mt-6 space-y-5">
                <li class="flex gap-4"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-[var(--accent-soft)] font-bold text-[var(--accent)]">1</span><div><b>Verify your email</b><p class="muted mt-1">Secure the account used to manage billing and workspace access.</p></div></li>
                <li class="flex gap-4"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-[var(--accent-soft)] font-bold text-[var(--accent)]">2</span><div><b>Connect WhatsApp</b><p class="muted mt-1">Create a session and scan the QR code from WhatsApp → Linked Devices.</p></div></li>
                <li class="flex gap-4"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-[var(--accent-soft)] font-bold text-[var(--accent)]">3</span><div><b>Try the real workflow</b><p class="muted mt-1">Use messaging, contacts, inbox and the trial features included in your current plan.</p></div></li>
                <li class="flex gap-4"><span class="grid size-8 shrink-0 place-items-center rounded-full bg-[var(--accent-soft)] font-bold text-[var(--accent)]">4</span><div><b>Choose a subscription</b><p class="muted mt-1">Upgrade from Billing when you are ready. Your data and linked-device credentials remain on the hosted platform.</p></div></li>
            </ol>

            <div class="mt-7 flex flex-wrap gap-3">
                @if(auth()->user()->hasVerifiedEmail())
                    <a class="btn-primary" href="{{ route('tenant.whatsapp.index') }}">Connect WhatsApp</a>
                    <a class="btn-secondary" href="{{ route('tenant.billing.show') }}">View plans</a>
                @else
                    <a class="btn-primary" href="{{ route('verification.notice') }}">Verify email first</a>
                @endif
            </div>
        </section>

        <aside class="space-y-6">
            <section class="app-card-subtle p-5">
                <p class="eyebrow">Current subscription</p>
                <h2 class="section-title mt-1">{{ $activeTenant->currentSubscription?->plan?->name ?? 'Trial' }}</h2>
                <p class="muted mt-2">Your workspace starts with a practical trial so you can test the actual WhatsApp flow before paying.</p>
            </section>

            <section class="app-card-subtle p-5">
                <p class="eyebrow">Hosted SaaS</p>
                <p class="muted mt-2">No source-code purchase or client-side server installation is required. Updates, connector recovery and platform operations are managed centrally.</p>
            </section>
        </aside>
    </div>
</x-layouts.app>
