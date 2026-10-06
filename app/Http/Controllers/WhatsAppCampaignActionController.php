<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\WhatsAppCampaignLifecycleService;
use App\Services\Campaigns\WhatsAppCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class WhatsAppCampaignActionController extends Controller
{
    public function duplicate(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignService $s): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('duplicate', $campaign);
        $r->validate(['idempotency_key' => ['required', 'string', 'max:80']]);
        $copy = $s->duplicate($campaign, $r->user(), $r->string('idempotency_key'));

        return redirect()->route('tenant.campaigns.show', $copy);
    }

    public function validateCampaign(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignService $s): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('update', $campaign);
        $v = $r->validate(['expected_version' => ['required', 'integer']]);
        [, $result] = $s->markReady($campaign, $r->user(), $v['expected_version']);

        return back()->with($result->valid() ? 'success' : 'error', $result->valid() ? 'Campaign is ready.' : implode(' ', $result->errors));
    }

    public function schedule(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignLifecycleService $l): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('schedule', $campaign);
        $v = $r->validate(['expected_version' => ['required', 'integer'], 'idempotency_key' => ['required', 'string', 'max:80']]);
        if (! $campaign->scheduled_at_utc) {
            throw ValidationException::withMessages(['scheduled_at_local' => 'Configure a future schedule before scheduling.']);
        }$l->transition($campaign, WhatsAppCampaignStatus::Scheduled, WhatsAppCampaignEvent::Scheduled, $r->user(), $v['expected_version']);

        return back()->with('success', 'Campaign scheduled; no execution job was dispatched.');
    }

    public function unschedule(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignLifecycleService $l): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('unschedule', $campaign);
        $v = $r->validate(['expected_version' => ['required', 'integer'], 'idempotency_key' => ['required', 'string', 'max:80']]);
        $l->transition($campaign, WhatsAppCampaignStatus::Ready, WhatsAppCampaignEvent::Unscheduled, $r->user(), $v['expected_version']);

        return back()->with('success', 'Campaign unscheduled.');
    }

    public function archive(Request $r, WhatsAppCampaign $campaign, TenantContext $ctx, WhatsAppCampaignLifecycleService $l): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('archive', $campaign);
        $v = $r->validate(['expected_version' => ['required', 'integer'], 'idempotency_key' => ['required', 'string', 'max:80']]);
        $l->transition($campaign, WhatsAppCampaignStatus::Archived, WhatsAppCampaignEvent::Archived, $r->user(), $v['expected_version']);

        return back()->with('success', 'Campaign archived.');
    }

    private function owned(WhatsAppCampaign $c, TenantContext $ctx): void
    {
        abort_unless($c->tenant_id === $ctx->id(), 404);
    }
}
