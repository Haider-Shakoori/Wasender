<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppCampaignExecutionStatus;
use App\Jobs\ProcessWhatsAppCampaignRecipient;
use App\Models\WhatsAppCampaignRecipientExecution;
use Illuminate\Console\Command;

final class DispatchDueWhatsAppCampaignRetries extends Command
{
    protected $signature = 'whatsapp-campaigns:dispatch-due-retries {--limit=100}';

    protected $description = 'Dispatch bounded due campaign recipient retries without calling Node.';

    public function handle(): int
    {
        $rows = WhatsAppCampaignRecipientExecution::where('status', 'retry_scheduled')->where('next_attempt_at', '<=', now())->whereHas('execution', fn ($q) => $q->where('status', WhatsAppCampaignExecutionStatus::Running))->orderBy('next_attempt_at')->limit((int) $this->option('limit'))->get();
        foreach ($rows as $row) {
            ProcessWhatsAppCampaignRecipient::dispatch($row->id)->onQueue(config('whatsapp_campaign_execution.queues.retry'));
        }$this->info("Queued {$rows->count()} retry job(s).");

        return self::SUCCESS;
    }
}
