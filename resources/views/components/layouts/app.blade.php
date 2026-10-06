<!doctype html>
<html lang="en" class="bg-[var(--page)]" data-theme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Dashboard' }} · {{ config('saas.product_name') }}</title>
    <script>
        (() => { const allowed=['light','dark','system']; let mode=localStorage.getItem('wasender-theme'); if(!allowed.includes(mode)) mode='system'; const dark=mode==='dark'||(mode==='system'&&matchMedia('(prefers-color-scheme: dark)').matches); document.documentElement.classList.toggle('dark',dark); document.documentElement.dataset.theme=mode; })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ mobileOpen:false, userOpen:false }" x-effect="document.body.style.overflow = mobileOpen ? 'hidden' : ''" @keydown.escape.window="mobileOpen=false;userOpen=false">
<a href="#main-content" class="sr-only z-[100] rounded-lg bg-[var(--accent)] px-4 py-2 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to main content</a>
<div class="min-h-screen lg:grid lg:grid-cols-[272px_minmax(0,1fr)]">
    <div x-cloak x-show="mobileOpen" x-transition.opacity class="fixed inset-0 z-40 bg-[var(--overlay)] lg:hidden" @click="mobileOpen=false" aria-hidden="true"></div>
    <aside id="app-navigation" class="fixed inset-y-0 left-0 z-50 flex w-[min(88vw,288px)] -translate-x-full flex-col bg-[var(--sidebar)] text-[var(--sidebar-text)] shadow-2xl transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:w-auto lg:translate-x-0 lg:shadow-none" :class="mobileOpen && 'translate-x-0'" aria-label="Application navigation">
        <div class="flex h-18 items-center justify-between border-b border-white/8 px-5">
            <a href="{{ route('tenant.dashboard') }}" class="flex min-w-0 items-center gap-3 rounded-lg">
                <span class="grid size-9 place-items-center rounded-xl bg-[var(--accent)] text-sm font-black text-[var(--accent-ink)]">W</span>
                <span class="min-w-0"><span class="block truncate text-sm font-semibold">{{ config('saas.product_name') }}</span><span class="block truncate text-[11px] text-[var(--sidebar-muted)]">Control plane</span></span>
            </a>
            <button class="grid size-9 place-items-center rounded-lg text-[var(--sidebar-muted)] hover:bg-white/8 hover:text-white lg:hidden" @click="mobileOpen=false" aria-label="Close navigation">×</button>
        </div>
        <div class="border-b border-white/8 p-3"><x-tenant-switcher /></div>
        <nav class="flex-1 overflow-y-auto px-3 py-5">
            @php($groups = [
                'Overview' => [
                    ['Dashboard', 'overview', route('tenant.dashboard'), null, false],
                    ['Analytics', 'overview', route('tenant.analytics.dashboard'), 'analytics.view', false],
                ],
                'Communication' => [
                    ['WhatsApp sessions', 'signal', route('tenant.whatsapp.index'), 'sessions.view', false],
                    ['Messages', 'message', route('tenant.messages.index'), 'messages.view', false],
                    ['Shared Inbox', 'message', route('tenant.inbox.overview'), 'inbox.view', false],
                    ['Saved Replies', 'message', route('tenant.inbox.saved-replies.index'), 'inbox.manage_saved_replies', false],
                    ['Contacts', 'people', route('tenant.contacts.index'), 'contacts.view', false],
                    ['Campaigns', 'send', route('tenant.campaigns.index'), 'campaigns.view', false],
                    ['Message Templates', 'message', route('tenant.message-templates.index'), 'whatsapp_templates.view', false],
                    ['Automations', 'automation', route('tenant.automations.index'), 'automations.view', false],
                    ['Chatbots', 'message', route('tenant.chatbots.index'), 'chatbots.view', false],
                    ['Integrations', 'hook', route('tenant.integrations.index'), 'integrations.view', false],
                ],
                'Developer' => [
                    ['API keys', 'key', null, 'api_keys.view', true],
                    ['Webhooks', 'hook', null, 'webhooks.view', true],
                ],
                'Workspace' => [
                    ['Team', 'team', route('tenant.team.index'), 'team.view', false],
                    ['Roles', 'roles', route('tenant.roles.index'), 'roles.view', false],
                    ['Audit Logs', 'audit', route('tenant.audit.index'), 'audit_logs.view', false],
                    ['Settings', 'settings', route('tenant.settings.edit'), 'settings.manage', false],
                    ['Billing', 'billing', route('tenant.billing.show'), 'billing.view', false],
                    ['Account security', 'key', route('tenant.account.security'), null, false],
                ],
            ])
            @foreach($groups as $group => $items)
                @php($visible = collect($items)->filter(fn($item) => $item[3] === null || $tenantPermissions->contains($item[3])))
                @if($visible->isNotEmpty())
                    <div class="mb-6"><p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[.16em] text-[var(--sidebar-muted)]">{{ $group }}</p><div class="space-y-1">
                    @foreach($visible as [$label,$icon,$href,$permission,$disabled])
                        @if($disabled)<div class="nav-disabled"><span class="grid size-5 place-items-center text-xs" aria-hidden="true">◇</span><span class="min-w-0 flex-1 truncate">{{ $label }}</span><span class="rounded-md bg-white/6 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide">Later</span></div>
                        @else<a class="nav-link {{ request()->url() === $href || ($href !== route('tenant.dashboard') && request()->is(trim(parse_url($href, PHP_URL_PATH), '/').'*')) ? 'nav-active' : '' }}" href="{{ $href }}" @if(request()->url() === $href) aria-current="page" @endif><span class="grid size-5 place-items-center text-xs" aria-hidden="true">◆</span><span class="truncate">{{ $label }}</span></a>@endif
                    @endforeach
                    </div></div>
                @endif
            @endforeach
        </nav>
        <div class="border-t border-white/8 p-3"><a class="nav-link" href="mailto:{{ config('saas.support_email') }}"><span aria-hidden="true">?</span> Help & support</a></div>
    </aside>
    <div class="min-w-0">
        <header class="sticky top-0 z-30 flex h-18 items-center justify-between border-b bg-[color:color-mix(in_srgb,var(--page)_88%,transparent)] px-4 backdrop-blur-xl sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3"><button class="grid size-10 place-items-center rounded-xl border bg-[var(--surface)] text-xl lg:hidden" @click="mobileOpen=true" aria-controls="app-navigation" :aria-expanded="mobileOpen" aria-label="Open navigation">≡</button><div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $title ?? 'Dashboard' }}</p><p class="truncate text-xs text-[var(--text-muted)]">{{ $activeTenant->name }}</p></div></div>
            <div class="flex items-center gap-2" x-data="theme">
                <div class="relative"><button class="btn-ghost size-10 px-0" @click="$refs.themeMenu.togglePopover()" aria-label="Change appearance">◐</button><div popover x-ref="themeMenu" class="m-0 ml-auto mt-2 w-40 rounded-xl border bg-[var(--surface-raised)] p-1.5 text-sm text-[var(--text)] shadow-xl">@foreach(['system'=>'System','light'=>'Light','dark'=>'Dark'] as $value=>$label)<button class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left hover:bg-[var(--interactive)]" @click="set('{{ $value }}');$refs.themeMenu.hidePopover()">{{ $label }}<span x-show="mode==='{{ $value }}'" class="text-[var(--accent)]">✓</span></button>@endforeach</div></div>
                <div class="relative"><button class="flex items-center gap-2 rounded-xl p-1.5 hover:bg-[var(--interactive)]" @click="userOpen=!userOpen" :aria-expanded="userOpen" aria-haspopup="menu"><span class="grid size-8 place-items-center rounded-lg bg-[var(--accent-soft)] text-xs font-bold text-[var(--accent)]">{{ str(auth()->user()->name)->substr(0,1)->upper() }}</span><span class="hidden max-w-36 truncate text-sm font-medium sm:block">{{ auth()->user()->name }}</span><span aria-hidden="true" class="text-[var(--text-muted)]">⌄</span></button><div x-cloak x-show="userOpen" x-transition @click.outside="userOpen=false" role="menu" class="absolute right-0 mt-2 w-64 rounded-xl border bg-[var(--surface-raised)] p-2 shadow-xl"><div class="border-b px-3 py-2"><p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="truncate text-xs text-[var(--text-muted)]">{{ auth()->user()->email }}</p></div><div class="px-3 py-2 text-xs text-[var(--text-muted)]">Role · {{ $currentTenantMembership->role->name }}</div><form method="post" action="{{ route('logout') }}">@csrf<button class="btn-ghost w-full justify-start" role="menuitem">Sign out</button></form></div></div>
            </div>
        </header>
        @if(session('status'))<div class="fixed right-4 top-20 z-[70] max-w-sm" x-data="{show:true}" x-show="show" x-transition><x-alert variant="success"><div class="flex items-start gap-4"><span class="flex-1">{{ session('status') }}</span><button @click="show=false" aria-label="Dismiss notification">×</button></div></x-alert></div>@endif
        <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1520px] p-4 sm:p-6 lg:p-8">{{ $slot }}</main>
    </div>
</div>
</body>
</html>
