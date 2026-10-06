<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Campaigns\CreateWhatsAppCampaignData;
use App\Data\Campaigns\UpdateWhatsAppCampaignData;
use App\Http\Requests\StoreWhatsAppCampaignRequest;
use App\Http\Requests\UpdateWhatsAppCampaignRequest;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\ContactSegment;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppSession;
use App\Services\Campaigns\WhatsAppCampaignQuery;
use App\Services\Campaigns\WhatsAppCampaignService;
use App\Services\Campaigns\WhatsAppCampaignStatusPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class WhatsAppCampaignController extends Controller
{
    public function index(Request $r, WhatsAppCampaignQuery $q)
    {
        $this->authorize('viewAny', WhatsAppCampaign::class);

        return view('tenant.campaigns.index', ['campaigns' => $q->paginate($r->only(['search', 'status', 'message_type', 'audience_type', 'schedule_type', 'needs_attention', 'date_from', 'date_to', 'archived', 'sort', 'direction']))]);
    }

    public function create(TenantContext $ctx)
    {
        $this->authorize('create', WhatsAppCampaign::class);

        return view('tenant.campaigns.form', $this->options($ctx->id()));
    }

    public function store(StoreWhatsAppCampaignRequest $r, WhatsAppCampaignService $s): RedirectResponse
    {
        $this->authorize('create', WhatsAppCampaign::class);
        $c = $s->create(CreateWhatsAppCampaignData::from($r->validated()), $r->user(), $r->file('attachment'));

        return redirect()->route('tenant.campaigns.show', $c)->with('success', 'Campaign draft created.');
    }

    public function show(WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignStatusPresenter $presenter)
    {
        abort_unless($campaign->tenant_id === $ctx->id(), 404);
        $this->authorize('view', $campaign);

        $campaign->load(['creator', 'attachment', 'events', 'sessionSelections.session', 'activePreparation', 'activeExecution']);
        $currentPreparation = $campaign->activePreparation ?: $campaign->preparations()->latest('id')->first();

        return view('tenant.campaigns.show', ['campaign' => $campaign, 'currentPreparation' => $currentPreparation, 'statusPresentation' => $presenter->present($campaign->status), 'exclusionSummary' => $currentPreparation ? $campaign->exclusions()->where('preparation_id', $currentPreparation->id)->selectRaw('reason_code, count(*) as total')->groupBy('reason_code')->pluck('total', 'reason_code') : collect()]);
    }

    public function edit(WhatsAppCampaign $campaign, TenantContext $ctx)
    {
        abort_unless($campaign->tenant_id === $ctx->id(), 404);
        $this->authorize('update', $campaign);

        return view('tenant.campaigns.form', [...$this->options($ctx->id()), 'campaign' => $campaign->load(['sessionSelections.session', 'audienceReferences'])]);
    }

    public function update(UpdateWhatsAppCampaignRequest $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignService $s): RedirectResponse
    {
        abort_unless($campaign->tenant_id === $ctx->id(), 404);
        $this->authorize('update', $campaign);
        $s->update($campaign, UpdateWhatsAppCampaignData::from($r->validated()), $r->user(), $r->file('attachment'));

        return redirect()->route('tenant.campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    private function options(int $tenant): array
    {
        return ['sessions' => WhatsAppSession::forTenant($tenant)->whereNull('deleted_at')->orderBy('name')->get(), 'groups' => ContactGroup::where('tenant_id', $tenant)->orderBy('name')->get(), 'labels' => ContactLabel::where('tenant_id', $tenant)->orderBy('name')->get(), 'segments' => ContactSegment::where('tenant_id', $tenant)->orderBy('name')->get(), 'contacts' => Contact::where('tenant_id', $tenant)->whereNull('deleted_at')->orderBy('display_name')->limit(config('whatsapp_campaigns.max_manual_contacts'))->get(), 'messageTemplates' => WhatsAppMessageTemplate::forTenant($tenant)->where('status', 'published')->whereNotNull('current_published_version_id')->with('currentPublishedVersion:id,whatsapp_message_template_id,uuid,version_number')->orderBy('name')->limit(100)->get(['id', 'uuid', 'name', 'type', 'current_published_version_id'])];
    }
}
