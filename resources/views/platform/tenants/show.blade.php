<x-layouts.platform :title="$tenant->name">
    <x-page-header eyebrow="Tenant record" :title="$tenant->name" :description="$tenant->uuid">
        <a class="btn-secondary" href="{{ route('platform.tenants.index') }}">Back</a>
    </x-page-header>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            <section class="panel">
                <div class="flex items-center justify-between">
                    <div><p class="eyebrow">Commercial</p><h2 class="section-title mt-1">Tenant subscription</h2></div>
                    @if($tenant->currentSubscription)
                        <x-badge :variant="$tenant->currentSubscription->status->permitsAccess() ? 'success' : 'danger'">
                            {{ $tenant->currentSubscription->status->value }}
                        </x-badge>
                    @endif
                </div>

                @if($tenant->currentSubscription)
                    <div class="mt-4 app-card-subtle p-4">
                        <b>{{ $tenant->currentSubscription->plan->name }}</b>
                        <p class="muted">Started {{ $tenant->currentSubscription->starts_at->format('M j, Y') }}</p>
                    </div>
                @else
                    <p class="muted mt-4">No current subscription.</p>
                @endif

                <form method="post" action="{{ route('platform.tenants.subscription.assign', $tenant) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <label class="text-sm">Plan
                        <select class="field mt-2" name="plan_uuid" required>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->uuid }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-input label="Trial days" name="trial_days" type="number" min="0" value="0" />
                    <div class="sm:col-span-2"><x-input label="Assignment reason" name="reason" required /></div>
                    <button class="btn-primary w-fit">Assign plan</button>
                </form>

                @if($tenant->currentSubscription)
                    <form method="post" action="{{ route('platform.tenants.subscription.status', $tenant) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <label class="text-sm">New status
                            <select class="field mt-2" name="status" required>
                                <option value="">Choose status</option>
                                @foreach($allowedSubscriptionStatuses as $status)
                                    <option value="{{ $status }}">{{ str($status)->headline() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-input label="Transition reason" name="reason" required />
                        <button class="btn-secondary w-fit">Update status</button>
                    </form>
                    @error('status')
                        <p class="mt-2 text-sm text-[var(--danger)]">{{ $message }}</p>
                    @enderror
                @endif
            </section>

            <section class="panel">
                <h2 class="section-title">Workspace profile</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><dt class="muted">Owner</dt><dd>{{ $tenant->owner->name }} · {{ $tenant->owner->email }}</dd></div>
                    <div><dt class="muted">Members</dt><dd>{{ $tenant->memberships_count }}</dd></div>
                    <div><dt class="muted">Status</dt><dd>{{ $tenant->status->label() }}</dd></div>
                    <div><dt class="muted">Created</dt><dd>{{ $tenant->created_at->format('M j, Y') }}</dd></div>
                </dl>
            </section>

            <section class="panel">
                <h2 class="section-title">Internal notes</h2>
                <div class="my-4 space-y-3">
                    @forelse($tenant->platformNotes->sortByDesc('created_at') as $note)
                        <article class="app-card-subtle p-4">
                            <p class="whitespace-pre-line text-sm">{{ $note->body }}</p>
                            <small class="text-[var(--text-muted)]">{{ $note->author->name }} · {{ $note->created_at->diffForHumans() }}</small>
                        </article>
                    @empty
                        <p class="muted">No internal notes.</p>
                    @endforelse
                </div>
                <form method="post" action="{{ route('platform.notes.store', ['tenant', $tenant->uuid]) }}">
                    @csrf
                    <label class="text-sm font-semibold" for="body">Add internal note</label>
                    <textarea id="body" name="body" class="field mt-2" rows="3" required maxlength="5000"></textarea>
                    <button class="btn-primary mt-3">Add note</button>
                </form>
            </section>
        </div>

        <aside class="panel h-fit">
            <h2 class="section-title">Lifecycle controls</h2>
            @if($tenant->is_active)
                <form method="post" action="{{ route('platform.tenants.suspend', $tenant) }}" class="mt-4">
                    @csrf
                    <label class="text-sm font-semibold" for="reason">Suspension reason</label>
                    <textarea id="reason" name="reason" class="field mt-2" required minlength="5" maxlength="500"></textarea>
                    <button class="btn-danger mt-3">Suspend tenant</button>
                </form>
            @else
                <p class="muted mt-3">{{ $tenant->suspension_reason }}</p>
                <form method="post" action="{{ route('platform.tenants.reactivate', $tenant) }}" class="mt-4">
                    @csrf
                    <button class="btn-primary">Reactivate tenant</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.platform>
