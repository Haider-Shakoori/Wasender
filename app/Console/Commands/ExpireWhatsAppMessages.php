<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageLifecycleService;
use Illuminate\Console\Command;

final class ExpireWhatsAppMessages extends Command
{
    protected $signature = 'whatsapp-messages:expire';

    protected $description = 'Expire queued outbound WhatsApp messages past their deadline';

    public function handle(WhatsAppMessageLifecycleService $lifecycle): int
    {
        WhatsAppMessage::whereIn('status', [WhatsAppMessageStatus::Queued, WhatsAppMessageStatus::Processing])->where('expires_at', '<=', now())
            ->eachById(fn (WhatsAppMessage $message) => $lifecycle->transition($message, WhatsAppMessageStatus::Expired, 'scheduler'));

        return self::SUCCESS;
    }
}
