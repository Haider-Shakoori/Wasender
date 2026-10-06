<x-layouts.platform title="Payment gateways">
    <x-page-header eyebrow="Billing" title="Payment gateways" description="Configure online billing providers without coupling subscriptions to one gateway." />

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="panel">
            @php($stripe = $gateways->get('stripe'))
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="eyebrow">Stripe</p>
                    <h2 class="section-title mt-1">Stripe subscriptions</h2>
                    <p class="muted mt-2">Checkout Sessions, signed webhooks, renewals and payment-failure grace handling.</p>
                </div>
                <x-badge :variant="$stripe?->is_enabled ? 'success' : 'neutral'">{{ $stripe?->is_enabled ? 'Enabled' : 'Disabled' }}</x-badge>
            </div>

            <form method="post" action="{{ route('platform.payment-gateways.update', 'stripe') }}" class="mt-6 grid gap-4 md:grid-cols-2">
                @csrf
                @method('put')

                <label class="text-sm">Mode
                    <select class="field mt-2" name="mode">
                        <option value="test" @selected(($stripe?->mode ?? 'test') === 'test')>Test</option>
                        <option value="live" @selected(($stripe?->mode ?? 'test') === 'live')>Live</option>
                    </select>
                </label>

                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked($stripe?->is_enabled)> Enable Stripe</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1" @checked($stripe?->is_default)> Default online gateway</label>
                </div>

                <label class="text-sm md:col-span-2">Publishable key
                    <input class="field mt-2" type="password" name="publishable_key" autocomplete="new-password" placeholder="{{ $stripe?->credentials_encrypted ? 'Stored securely — enter only to replace' : 'pk_test_…' }}">
                </label>
                <label class="text-sm md:col-span-2">Secret key
                    <input class="field mt-2" type="password" name="secret_key" autocomplete="new-password" placeholder="{{ $stripe?->credentials_encrypted ? 'Stored securely — enter only to replace' : 'sk_test_…' }}">
                </label>
                <label class="text-sm md:col-span-2">Webhook signing secret
                    <input class="field mt-2" type="password" name="webhook_secret" autocomplete="new-password" placeholder="{{ $stripe?->credentials_encrypted ? 'Stored securely — enter only to replace' : 'whsec_…' }}">
                </label>

                <div class="app-card-subtle p-4 md:col-span-2">
                    <p class="text-sm font-semibold">Webhook endpoint</p>
                    <p class="muted mt-1 break-all">{{ route('billing.webhooks.stripe') }}</p>
                    <p class="muted mt-2 text-xs">Subscribe at minimum to checkout.session.completed, invoice.paid and invoice.payment_failed.</p>
                </div>

                <div class="md:col-span-2">
                    <button class="btn-primary">Save Stripe configuration</button>
                </div>
            </form>
        </section>

        <aside class="space-y-6">
            <section class="app-card-subtle p-5">
                <p class="eyebrow">Manual payments</p>
                <h2 class="section-title mt-1">Always available to platform admins</h2>
                <p class="muted mt-2">Manual payments remain supported for bank transfer or local/offline payment workflows.</p>
            </section>

            <section class="app-card-subtle p-5">
                <p class="eyebrow">Security</p>
                <p class="muted mt-2">Gateway secrets are encrypted at rest and never rendered back into the page. Webhook signatures are verified before billing state changes.</p>
                @if($stripe?->last_webhook_at)
                    <p class="mt-4 text-sm font-semibold">Last Stripe webhook</p>
                    <p class="muted mt-1">{{ $stripe->last_webhook_at->diffForHumans() }} · {{ $stripe->last_webhook_status }}</p>
                @endif
            </section>
        </aside>
    </div>
</x-layouts.platform>
