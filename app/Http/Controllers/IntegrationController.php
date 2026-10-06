<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppSession;
use App\Services\AuditService;
use App\Services\Integrations\IntegrationUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class IntegrationController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $q = Integration::forTenant($context->id())->when($request->string('search')->trim()->toString(), fn ($q, $v) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%'))->when($request->input('provider'), fn ($q, $v) => $q->where('provider', $v))->when($request->input('status'), fn ($q, $v) => $q->where('status', $v));

        return view('tenant.integrations.index', ['integrations' => $q->latest('updated_at')->paginate(15)->withQueryString()]);
    }

    public function create(TenantContext $context): View
    {
        return $this->form(new Integration(['provider' => 'generic_api', 'status' => 'active', 'is_enabled' => true]), $context);
    }

    public function store(Request $request, TenantContext $context, IntegrationUrlGuard $urls, AuditService $audit): RedirectResponse
    {
        $integration = new Integration;
        $secrets = $this->save($request, $context, $integration, $urls, true);
        $audit->recordDomain('integration.credentials_created', auth()->user(), $integration->tenant, $integration, ['provider' => $integration->provider->value]);

        return redirect()->route('tenant.integrations.show', $integration)->with('generated_credentials', $secrets)->with('status', 'Integration created. Copy the credentials now.');
    }

    public function show(Integration $integration, TenantContext $context): View
    {
        $this->owned($integration, $context);
        $integration->load(['events' => fn ($q) => $q->latest()->limit(50), 'deliveries' => fn ($q) => $q->latest()->limit(50)]);

        return view('tenant.integrations.show', compact('integration'));
    }

    public function edit(Integration $integration, TenantContext $context): View
    {
        $this->owned($integration, $context);

        return $this->form($integration, $context);
    }

    public function update(Request $request, Integration $integration, TenantContext $context, IntegrationUrlGuard $urls): RedirectResponse
    {
        $this->owned($integration, $context);
        $this->save($request, $context, $integration, $urls, false);

        return redirect()->route('tenant.integrations.show', $integration)->with('status', 'Integration updated.');
    }

    public function toggle(Integration $integration, TenantContext $context, AuditService $audit): RedirectResponse
    {
        $this->owned($integration, $context);
        $integration->update(['is_enabled' => ! $integration->is_enabled, 'status' => $integration->is_enabled ? 'disabled' : 'active', 'updated_by' => auth()->id()]);
        $audit->recordDomain($integration->is_enabled ? 'integration.enabled' : 'integration.disabled', auth()->user(), $integration->tenant, $integration, ['provider' => $integration->provider->value]);

        return back()->with('status', $integration->is_enabled ? 'Integration enabled.' : 'Integration disabled.');
    }

    public function archive(Integration $integration, TenantContext $context, AuditService $audit): RedirectResponse
    {
        $this->owned($integration, $context);
        $integration->update(['is_enabled' => false, 'status' => 'archived', 'updated_by' => auth()->id()]);
        $audit->recordDomain('integration.disabled', auth()->user(), $integration->tenant, $integration, ['provider' => $integration->provider->value, 'reason' => 'archived']);

        return back()->with('status', 'Integration archived.');
    }

    public function rotate(Integration $integration, TenantContext $context, AuditService $audit): RedirectResponse
    {
        $this->owned($integration, $context);
        $token = Str::random(64);
        $secret = bin2hex(random_bytes(32));
        $integration->update(['credentials_encrypted' => ['token_hash' => hash('sha256', $token), 'webhook_secret' => $secret], 'updated_by' => auth()->id()]);
        $audit->recordDomain('integration.credentials_rotated', auth()->user(), $integration->tenant, $integration, ['provider' => $integration->provider->value]);

        return back()->with('generated_credentials', ['token' => $token, 'webhook_secret' => $secret])->with('status', 'Credentials rotated. Copy them now.');
    }

    public function revoke(Integration $integration, TenantContext $context, AuditService $audit): RedirectResponse
    {
        $this->owned($integration, $context);
        $integration->update(['credentials_encrypted' => null, 'is_enabled' => false, 'status' => 'disabled', 'updated_by' => auth()->id()]);
        $audit->recordDomain('integration.credentials_revoked', auth()->user(), $integration->tenant, $integration, ['provider' => $integration->provider->value]);

        return back()->with('status', 'Credentials revoked and integration disabled.');
    }

    private function save(Request $request, TenantContext $context, Integration $integration, IntegrationUrlGuard $urls, bool $creating): array
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'provider' => ['required', Rule::enum(IntegrationProvider::class), Rule::notIn(['shopify'])], 'base_url' => 'nullable|url:https|max:500', 'destination_url' => 'nullable|url:https|max:500', 'store_url' => 'nullable|url:https|max:500', 'default_session_uuid' => 'required|uuid', 'webhook_secret' => 'nullable|string|min:16|max:255', 'order_created_template' => 'nullable|uuid', 'order_processing_template' => 'nullable|uuid', 'order_completed_template' => 'nullable|uuid', 'order_cancelled_template' => 'nullable|uuid', 'order_refunded_template' => 'nullable|uuid', 'payment_received_template' => 'nullable|uuid']);
        $session = WhatsAppSession::forTenant($context->id())->where('uuid', $data['default_session_uuid'])->firstOrFail();
        $provider = $data['provider'];
        if ($provider === 'webhook') {
            $urls->validate($data['destination_url'] ?? '');
        }
        $configuration = ['default_session_uuid' => $session->uuid];
        foreach (['base_url', 'destination_url', 'store_url'] as $key) {
            if (filled($data[$key] ?? null)) {
                $configuration[$key] = rtrim($data[$key], '/');
            }
        }
        $eventTemplates = [];
        foreach (['order_created' => 'order.created', 'order_processing' => 'order.processing', 'order_completed' => 'order.completed', 'order_cancelled' => 'order.cancelled', 'order_refunded' => 'order.refunded', 'payment_received' => 'payment.received'] as $field => $event) {
            if ($uuid = $data[$field.'_template'] ?? null) {
                $template = WhatsAppMessageTemplate::forTenant($context->id())->where('uuid', $uuid)->where('status', 'published')->with('currentPublishedVersion')->firstOrFail();
                data_set($eventTemplates, $event, ['template_uuid' => $template->uuid, 'template_version_uuid' => $template->currentPublishedVersion->uuid]);
            }
        }
        $configuration['event_templates'] = $eventTemplates;
        $secrets = [];
        $credentials = $integration->credentials_encrypted ?? [];
        if ($creating) {
            $token = Str::random(64);
            $secret = $data['webhook_secret'] ?? bin2hex(random_bytes(32));
            $credentials = ['token_hash' => hash('sha256', $token), 'webhook_secret' => $secret];
            $secrets = ['token' => $token, 'webhook_secret' => $secret];
        } elseif (filled($data['webhook_secret'] ?? null)) {
            $credentials['webhook_secret'] = $data['webhook_secret'];
        }
        $integration->fill(['tenant_id' => $context->id(), 'provider' => $provider, 'name' => $data['name'], 'status' => 'active', 'is_enabled' => true, 'configuration' => $configuration, 'credentials_encrypted' => $credentials, 'created_by' => $integration->created_by ?? auth()->id(), 'updated_by' => auth()->id()])->save();

        return $secrets;
    }

    private function form(Integration $integration, TenantContext $context): View
    {
        return view('tenant.integrations.form', ['integration' => $integration, 'sessions' => WhatsAppSession::forTenant($context->id())->where('status', 'ready')->orderBy('name')->get(['uuid', 'name']), 'templates' => WhatsAppMessageTemplate::forTenant($context->id())->where('status', 'published')->whereNotNull('current_published_version_id')->orderBy('name')->get(['uuid', 'name'])]);
    }

    private function owned(Integration $integration, TenantContext $context): void
    {
        abort_unless($integration->tenant_id === $context->id(), 404);
    }
}
