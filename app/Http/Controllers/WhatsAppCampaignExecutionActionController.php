<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Campaigns\CampaignControlData;
use App\Data\Campaigns\LaunchWhatsAppCampaignData;
use App\Http\Requests\CampaignControlRequest;
use App\Http\Requests\CancelWhatsAppCampaignRequest;
use App\Http\Requests\LaunchWhatsAppCampaignRequest;
use App\Http\Requests\PauseWhatsAppCampaignRequest;
use App\Http\Requests\ResumeWhatsAppCampaignRequest;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\CancelWhatsAppCampaignExecutionService;
use App\Services\Campaigns\LaunchWhatsAppCampaignService;
use App\Services\Campaigns\PauseWhatsAppCampaignService;
use App\Services\Campaigns\ResumeWhatsAppCampaignService;
use Illuminate\Http\RedirectResponse;

final class WhatsAppCampaignExecutionActionController extends Controller
{
    public function launch(LaunchWhatsAppCampaignRequest $r, WhatsAppCampaign $campaign, TenantContext $ctx, LaunchWhatsAppCampaignService $s): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('launch', $campaign);
        $s->launch($campaign, new LaunchWhatsAppCampaignData($r->string('idempotency_token'), $r->integer('expected_version')), $r->user());

        return back()->with('success', 'Campaign execution queued. No delivery is confirmed and Node transport is not active.');
    }

    public function pause(PauseWhatsAppCampaignRequest $r, WhatsAppCampaign $campaign, TenantContext $ctx, PauseWhatsAppCampaignService $s): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('pause', $campaign);
        $s->pause($campaign, $this->data($r), $r->user());

        return back()->with('success', 'Pause requested.');
    }

    public function resume(ResumeWhatsAppCampaignRequest $r, WhatsAppCampaign $campaign, TenantContext $ctx, ResumeWhatsAppCampaignService $s): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('resume', $campaign);
        $s->resume($campaign, $this->data($r), $r->user());

        return back()->with('success', 'Campaign execution resumed.');
    }

    public function cancel(CancelWhatsAppCampaignRequest $r, WhatsAppCampaign $campaign, TenantContext $ctx, CancelWhatsAppCampaignExecutionService $s): RedirectResponse
    {
        $this->owned($campaign, $ctx);
        $this->authorize('cancel', $campaign);
        $s->cancel($campaign, $this->data($r), $r->user());

        return back()->with('success', 'Cancellation requested. No Node cancellation was called.');
    }

    private function data(CampaignControlRequest $r): CampaignControlData
    {
        return new CampaignControlData($r->string('idempotency_token'), $r->integer('expected_version'), $r->string('reason')->toString() ?: null);
    }

    private function owned(WhatsAppCampaign $c, TenantContext $ctx): void
    {
        abort_unless($c->tenant_id === $ctx->id(), 404);
    }
}
