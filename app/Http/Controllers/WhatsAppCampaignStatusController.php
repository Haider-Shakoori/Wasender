<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use App\Services\Campaigns\WhatsAppCampaignStatusPresenter;
use Illuminate\Http\JsonResponse;

final class WhatsAppCampaignStatusController extends Controller
{
    public function __invoke(WhatsAppCampaign $campaign, TenantContext $context, WhatsAppCampaignStatusPresenter $presenter): JsonResponse
    {
        abort_unless($campaign->tenant_id === $context->id(), 404);
        $this->authorize('view', $campaign);
        $campaign->load(['activePreparation:id,status,progress_percentage,processed_candidates,total_candidates,eligible_count,excluded_count,failure_code', 'activeExecution:id,status,progress_percentage,pending_recipients,processing_recipients,retry_scheduled_recipients,transport_pending_recipients,unknown_recipients,sent_recipients,delivered_recipients,read_recipients,failed_recipients,last_connector_event_at,failure_code']);
        $status = $presenter->present($campaign->status);

        return response()->json([
            'campaign_status' => $campaign->status->value,
            'preparation_status' => $campaign->activePreparation?->status->value,
            'execution_status' => $campaign->activeExecution?->status->value,
            'progress' => $campaign->activeExecution?->progress_percentage ?? $campaign->activePreparation?->progress_percentage ?? $campaign->progress_percentage,
            'counters' => [
                'prepared' => $campaign->eligible_recipient_count, 'sent' => $campaign->sent_recipient_count,
                'delivered' => $campaign->delivered_recipient_count, 'read' => $campaign->read_recipient_count,
                'failed' => $campaign->failed_recipient_count, 'unknown' => $campaign->activeExecution?->unknown_recipients ?? 0,
            ],
            'warning' => $campaign->activeExecution?->unknown_recipients > 0 ? 'An uncertain transport result is being reconciled without resending.' : null,
            'allowed_actions' => $status['actions'],
            'terminal' => $status['terminal'],
            'last_update' => ($campaign->activeExecution?->last_connector_event_at ?? $campaign->updated_at)->toIso8601String(),
        ]);
    }
}
