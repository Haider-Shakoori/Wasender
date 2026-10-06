<x-layouts.app title="Overview">
    <x-page-header
        eyebrow="Workspace overview"
        title="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}"
        description="WhatsApp operations, conversations, campaigns and workspace activity in one place."
    >
        <x-slot:actions>
            @if($tenantPermissions->contains('sessions.manage'))
                <a class="btn-primary" href="{{ route('tenant.whatsapp.index') }}">Connect WhatsApp</a>
            @elseif($tenantPermissions->contains('inbox.view'))
                <a class="btn-primary" href="{{ route('tenant.inbox.overview') }}">Open inbox</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Communication metrics">
        <article class="app-card p-5">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-[var(--text-secondary)]">WhatsApp sessions</p>
                <span class="grid size-9 place-items-center rounded-xl bg-[var(--accent-soft)] text-[var(--accent)]">WA</span>
            </div>
            <p class="metric-value mt-5">{{ number_format($readyWhatsAppSessions) }}<span class="ml-1 text-base font-medium text-[var(--text-muted)]">/ {{ number_format($whatsappSessions) }}</span></p>
            <p class="mt-1 text-xs text-[var(--text-muted)]">{{ $readyWhatsAppSessions === $whatsappSessions && $whatsappSessions > 0 ? 'All connected' : 'Connected and ready' }}</p>
        </article>

        <article class="app-card p-5">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-[var(--text-secondary)]">Messages today</p>
                <span class="grid size-9 place-items-center rounded-xl bg-[var(--accent-soft)] text-[var(--accent)]">↗</span>
            </div>
            <p class="metric-value mt-5">{{ number_format($messagesToday) }}</p>
            <p class="mt-1 text-xs text-[var(--text-muted)]">{{ number_format($queuedMessages) }} currently queued or sending</p>
        </article>

        <article class="app-card p-5">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-[var(--text-secondary)]">Open conversations</p>
                <span class="grid size-9 place-items-center rounded-xl bg-[var(--accent-soft)] text-[var(--accent)]">◇</span>
            </div>
            <p class="metric-value mt-5">{{ number_format($openConversations) }}</p>
            <p class="mt-1 text-xs text-[var(--text-muted)]">{{ number_format($unreadMessages) }} unread messages</p>
        </article>

        <article class="app-card p-5">
            <div class="flex items-start justify-between">
                <p class="text-sm font-medium text-[var(--text-secondary)]">Active workflows</p>
                <span class="grid size-9 place-items-center rounded-xl bg-[var(--accent-soft)] text-[var(--accent)]">◎</span>
            </div>
            <p class="metric-value mt-5">{{ number_format($activeAutomations) }}</p>
            <p class="mt-1 text-xs text-[var(--text-muted)]">{{ number_format($activeCampaigns) }} campaigns ready, scheduled or running</p>
        </article>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(320px,.55fr)]">
        <section class="app-card overflow-hidden">
            <div class="flex flex-col justify-between gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                <div>
                    <p class="eyebrow">Live operations</p>
                    <h2 class="section-title mt-1">Quick access</h2>
                </div>
                <span class="text-xs text-[var(--text-muted)]">Everything routes through the same durable WhatsApp queue</span>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">
                @foreach([
                    ['Sessions','Connect, pause and recover linked devices',route('tenant.whatsapp.index'),'sessions.view','WA'],
                    ['Shared Inbox','Reply, assign and manage conversations',route('tenant.inbox.overview'),'inbox.view','IN'],
                    ['Messages','Direct messages and delivery states',route('tenant.messages.index'),'messages.view','MS'],
                    ['Campaigns','Audience, scheduling and progress',route('tenant.campaigns.index'),'campaigns.view','CP'],
                    ['Automations','Workflow executions and actions',route('tenant.automations.index'),'automations.view','AU'],
                    ['Chatbots','Rules, handoff and automated replies',route('tenant.chatbots.index'),'chatbots.view','BT'],
                ] as [$label,$description,$href,$permission,$icon])
                    @if($tenantPermissions->contains($permission))
                        <a href="{{ $href }}" class="app-card-subtle group p-4 transition hover:border-[var(--accent)]">
                            <span class="grid size-9 place-items-center rounded-xl bg-[var(--accent-soft)] text-xs font-bold text-[var(--accent)]">{{ $icon }}</span>
                            <h3 class="mt-4 font-semibold">{{ $label }}</h3>
                            <p class="muted mt-1 text-sm">{{ $description }}</p>
                            <p class="mt-4 text-xs font-semibold text-[var(--accent)]">Open →</p>
                        </a>
                    @endif
                @endforeach
            </div>
        </section>

        <aside class="space-y-6">
            <section class="app-card p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="eyebrow">Subscription</p>
                        <h2 class="section-title mt-1">{{ $subscription?->plan?->name ?? 'No active plan' }}</h2>
                    </div>
                    <x-badge :variant="$subscription && $subscription->status->permitsAccess() ? 'success' : 'danger'">
                        {{ $subscription?->status?->value ? str($subscription->status->value)->headline() : 'Inactive' }}
                    </x-badge>
                </div>
                @if($subscription)
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Billing interval</dt><dd class="font-medium">{{ str($subscription->plan->billing_interval->value)->headline() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Period ends</dt><dd class="font-medium">{{ $subscription->current_period_ends_at?->format('M j, Y') ?: 'No fixed end' }}</dd></div>
                    </dl>
                @endif
                @if($tenantPermissions->contains('billing.view'))
                    <a class="btn-secondary mt-5 w-full" href="{{ route('tenant.billing.show') }}">View billing</a>
                @endif
            </section>

            <section class="app-card p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">Workspace health</h2>
                    <x-badge variant="success">{{ $tenant->status->label() }}</x-badge>
                </div>
                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Team members</dt><dd class="font-medium">{{ number_format($activeMembers) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Pending invites</dt><dd class="font-medium">{{ number_format($pendingInvitations) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Custom roles</dt><dd class="font-medium">{{ number_format($customRoles) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Your role</dt><dd class="font-medium">{{ $membership->role->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-[var(--text-muted)]">Timezone</dt><dd class="truncate font-medium">{{ $tenant->timezone }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>

    <section class="app-card mt-6 overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4 sm:px-6">
            <div>
                <p class="eyebrow">Audit trail</p>
                <h2 class="section-title mt-1">Recent workspace activity</h2>
            </div>
            @if($tenantPermissions->contains('audit_logs.view'))
                <a class="text-sm font-semibold text-[var(--accent)] hover:underline" href="{{ route('tenant.audit.index') }}">View all</a>
            @endif
        </div>
        @if($tenantPermissions->contains('audit_logs.view') && $recentAuditLogs->isNotEmpty())
            <div class="divide-y">
                @foreach($recentAuditLogs as $log)
                    <a class="flex items-start gap-4 px-5 py-4 transition hover:bg-[var(--table-hover)] sm:px-6" href="{{ route('tenant.audit.show', $log->uuid) }}">
                        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-[var(--interactive)] text-xs text-[var(--accent)]">◆</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ str($log->action)->replace(['.','_'],' ')->headline() }}</span>
                            <span class="mt-0.5 block truncate text-xs text-[var(--text-muted)]">{{ $log->user?->name ?? 'System' }}</span>
                        </span>
                        <time class="shrink-0 text-xs text-[var(--text-muted)]">{{ $log->created_at->diffForHumans() }}</time>
                    </a>
                @endforeach
            </div>
        @elseif($tenantPermissions->contains('audit_logs.view'))
            <x-empty-state title="No activity yet" description="Workspace changes and security events will appear here." />
        @else
            <x-empty-state title="Activity is protected" description="Your role does not include access to the workspace audit trail." />
        @endif
    </section>
</x-layouts.app>
