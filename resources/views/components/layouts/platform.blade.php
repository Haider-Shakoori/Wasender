<!doctype html>
<html lang="en" class="bg-[var(--page)]" data-theme="system">
<head>
 <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark">
 <title>{{ $title ?? 'Platform' }} · {{ config('saas.product_name') }}</title>
 <script>(()=>{let m=localStorage.getItem('relay-theme')||'system';document.documentElement.classList.toggle('dark',m==='dark'||(m==='system'&&matchMedia('(prefers-color-scheme: dark)').matches));document.documentElement.dataset.theme=m})()</script>
 @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="platform-theme" x-data="{nav:false}" @keydown.escape.window="nav=false">
<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] btn-primary">Skip to content</a>
<div class="min-h-screen lg:grid lg:grid-cols-[252px_1fr]">
 <div x-cloak x-show="nav" class="fixed inset-0 z-40 bg-[var(--overlay)] lg:hidden" @click="nav=false"></div>
 <aside class="fixed inset-y-0 z-50 flex w-[min(86vw,272px)] -translate-x-full flex-col bg-[var(--sidebar)] text-white transition lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" :class="nav&&'translate-x-0'" aria-label="Platform navigation">
  <div class="border-b border-white/10 p-5"><a href="{{ route('platform.dashboard') }}" class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-[var(--accent)] font-black">P</span><span><b class="block text-sm">Platform Console</b><small class="text-[var(--sidebar-muted)]">{{ config('saas.product_name') }}</small></span></a></div>
  @php
      $authz = app(\App\Contracts\PlatformAuthorization::class);
      $u = auth()->user();
  @endphp
  <nav class="flex-1 space-y-1 overflow-y-auto p-3">
   @foreach([
    ['Overview','platform.dashboard','platform.dashboard.view'],
    ['Analytics','platform.analytics.dashboard','platform.analytics.view'],
    ['Chatbots','platform.chatbots.index','platform.chatbots.view'],
    ['Integrations','platform.integrations.index','platform.integrations.view'],
    ['Security','platform.security','platform.dashboard.view'],
    ['Tenants','platform.tenants.index','platform.tenants.view'],
    ['Users','platform.users.index','platform.users.view'],
    ['Audit trail','platform.audit.index','platform.audit.view'],
    ['Queues','platform.queues.index','platform.queues.view'],
    ['System health','platform.health','platform.health.view'],
    ['Operations','platform.operations','platform.health.view'],
    ['Plans','platform.plans.index','platform.plans.view'],
    ['Billing','platform.billing.index','platform.subscriptions.view'],
    ['Payment gateways','platform.payment-gateways.index','platform.subscriptions.manage'],
    ['WhatsApp sessions','platform.whatsapp.index','platform.whatsapp.view'],
    ['Outbound messages','platform.messages.index','platform.messages.view'],
    ['Message templates','platform.message-templates.index','platform.whatsapp_templates.view'],
    ['Contacts','platform.contacts.index','platform.contacts.view'],
    ['Campaigns','platform.campaigns.index','platform.campaigns.view'],
    ['Campaign preparations','platform.campaign-preparations.index','platform.campaigns.view_details'],
    ['Campaign executions','platform.campaign-executions.index','platform.campaign_executions.view'],
    ['Campaign attempts','platform.campaign-attempts.index','platform.campaign_executions.view'],
    ['Campaign connector','platform.campaign-connector.show','platform.health.view'],
    ['Automations','platform.automations.index','platform.automations.view'],
    ['Automation executions','platform.automation-executions.index','platform.automation_executions.view'],
    ['Shared inbox','platform.inbox.conversations.index','platform.inbox.view'],
    ['Inbox messages','platform.inbox.messages.index','platform.inbox.view_messages'],
    ['Inbox failures','platform.inbox.failures.index','platform.inbox.view_failures'],
   ] as [$label,$route,$permission])
    @if($authz->allows($u,$permission))<a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($route) || request()->routeIs(str($route)->beforeLast('.').'.*') ? 'nav-active':'' }}">{{ $label }}</a>@endif
   @endforeach
  </nav>
  <div class="border-t border-white/10 p-3"><a href="{{ route('tenant.dashboard') }}" class="nav-link">Return to workspace</a></div>
 </aside>
 <div class="min-w-0">
  <header class="sticky top-0 z-30 flex h-18 items-center justify-between border-b bg-[color:color-mix(in_srgb,var(--page)_88%,transparent)] px-4 backdrop-blur-xl sm:px-6 lg:px-8">
   <div class="flex items-center gap-3"><button class="btn-secondary size-10 px-0 lg:hidden" @click="nav=true" aria-label="Open navigation">☰</button><div><p class="text-sm font-semibold">{{ $title ?? 'Platform' }}</p><p class="text-xs text-[var(--text-muted)]">Global operations · no tenant context</p></div></div>
   <div class="flex items-center gap-2"><span class="hidden text-sm text-[var(--text-muted)] sm:block">{{ $u->name }}</span><form method="post" action="{{ route('logout') }}">@csrf<button class="btn-secondary">Sign out</button></form></div>
  </header>
  @if(session('status'))<div class="fixed right-4 top-20 z-50 max-w-sm"><x-alert variant="success">{{ session('status') }}</x-alert></div>@endif
  <main id="main-content" tabindex="-1" class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">{{ $slot }}</main>
 </div>
</div>
</body></html>
