<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Enums\WhatsAppCampaignRecipientExecutionStatus;
use App\Jobs\ProcessWhatsAppCampaignRecipient;
use App\Models\WhatsAppCampaignRecipientExecution;
use Illuminate\Validation\ValidationException;

final class RetryWhatsAppCampaignRecipientService
{
    public function retry(WhatsAppCampaignRecipientExecution $row): void
    {
        if ($row->execution->status !== WhatsAppCampaignExecutionStatus::Running || $row->status !== WhatsAppCampaignRecipientExecutionStatus::Failed) {
            throw ValidationException::withMessages(['recipient' => 'This recipient is not retryable in the current state.']);
        }if ($row->attempt_count >= $row->max_attempts) {
            throw ValidationException::withMessages(['recipient' => 'Attempt limit reached.']);
        }$row->forceFill(['status' => WhatsAppCampaignRecipientExecutionStatus::RetryScheduled, 'next_attempt_at' => now(), 'failure_code' => null, 'failure_message' => null])->save();
        ProcessWhatsAppCampaignRecipient::dispatch($row->id)->onQueue(config('whatsapp_campaign_execution.queues.retry'));
    }
}
