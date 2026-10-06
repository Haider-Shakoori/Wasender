<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Jobs\ProcessWhatsAppCampaignRecipient;
use App\Models\WhatsAppCampaignExecution;
use App\Models\WhatsAppCampaignRecipientExecution;
use Illuminate\Support\Facades\DB;

final class ClaimCampaignRecipientsService
{
    public function claim(WhatsAppCampaignExecution $execution): int
    {
        if ($execution->status !== WhatsAppCampaignExecutionStatus::Running) {
            return 0;
        }
        $inFlight = $execution->recipients()->whereIn('status', ['claimed', 'processing'])->count();
        $capacity = max(0, config('whatsapp_campaign_execution.campaign_concurrency') - $inFlight);
        $limit = min($capacity, config('whatsapp_campaign_execution.claim_size'));
        if ($limit < 1) {
            return 0;
        }
        $ids = DB::transaction(function () use ($execution, $limit) {
            $rows = WhatsAppCampaignRecipientExecution::where('campaign_execution_id', $execution->id)->where(function ($q) {
                $q->where('status', 'pending')->orWhere(fn ($r) => $r->where('status', 'retry_scheduled')->where('next_attempt_at', '<=', now()));
            })->orderBy('id')->lockForUpdate()->limit($limit)->get();
            foreach ($rows as $row) {
                $row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::Claimed, 'claimed_at' => now(), 'lock_version' => $row->lock_version + 1])->save();
            }

            return $rows->pluck('id')->all();
        });
        foreach ($ids as $id) {
            ProcessWhatsAppCampaignRecipient::dispatch($id);
        }
        $execution->forceFill(['pending_recipients' => $execution->recipients()->where('status', 'pending')->count(), 'processing_recipients' => $execution->recipients()->whereIn('status', ['claimed', 'processing'])->count(), 'last_heartbeat_at' => now()])->save();

        return count($ids);
    }
}
