<?php

namespace App\Services\Campaigns;

use App\Data\Campaigns\CampaignRetryDecision;
use App\Enums\WhatsAppCampaignFailureClass;
use Carbon\CarbonImmutable;

final class WhatsAppCampaignRetryPolicy
{
    public function decide(WhatsAppCampaignFailureClass $class, string $code, int $attempt, int $maximum): CampaignRetryDecision
    {
        if ($attempt >= $maximum || $class !== WhatsAppCampaignFailureClass::Transient && $class !== WhatsAppCampaignFailureClass::Transport) {
            return new CampaignRetryDecision(false, null, $attempt >= $maximum ? 'attempt_limit_reached' : $code);
        }
        $base = config('whatsapp_campaign_execution.retry_base_seconds');
        $delay = min($base * (2 ** max(0, $attempt - 1)), config('whatsapp_campaign_execution.retry_max_seconds'));
        $jitter = (int) round($delay * config('whatsapp_campaign_execution.retry_jitter_percent') / 100);
        $offset = $jitter ? random_int(-$jitter, $jitter) : 0;

        return new CampaignRetryDecision(true, CarbonImmutable::now()->addSeconds(max(1, $delay + $offset)), $code);
    }
}
