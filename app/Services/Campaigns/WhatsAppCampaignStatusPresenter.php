<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignStatus;

final class WhatsAppCampaignStatusPresenter
{
    public function present(WhatsAppCampaignStatus $s): array
    {
        return ['label' => str($s->value)->replace('_', ' ')->headline()->toString(), 'description' => $s->editable() ? 'Campaign configuration may be edited.' : ($s->terminal() ? 'Campaign lifecycle is complete.' : 'Campaign is not editable in this state.'), 'severity' => match ($s) {
            WhatsAppCampaignStatus::Ready,WhatsAppCampaignStatus::Completed => 'success',WhatsAppCampaignStatus::NeedsAttention,WhatsAppCampaignStatus::Failed => 'danger',WhatsAppCampaignStatus::Scheduled,WhatsAppCampaignStatus::Running => 'info',default => 'neutral'
        }, 'icon' => $s->terminal() ? 'check-circle' : 'clock', 'editable' => $s->editable(), 'terminal' => $s->terminal(), 'active' => ! $s->terminal() && ! $s->editable(), 'actions' => match ($s) {
            WhatsAppCampaignStatus::Draft,WhatsAppCampaignStatus::NeedsAttention => ['edit', 'validate', 'duplicate', 'archive'],
            WhatsAppCampaignStatus::Ready => ['edit', 'prepare', 'schedule', 'duplicate', 'archive'],
            WhatsAppCampaignStatus::Scheduled => ['prepare', 'unschedule', 'duplicate'],
            WhatsAppCampaignStatus::Preparing => ['cancel_preparation'],
            WhatsAppCampaignStatus::Prepared => ['launch', 'invalidate_preparation', 'duplicate'],
            WhatsAppCampaignStatus::Queued,WhatsAppCampaignStatus::Running,WhatsAppCampaignStatus::Pausing => ['pause', 'cancel'],
            WhatsAppCampaignStatus::Paused => ['resume', 'cancel'],
            WhatsAppCampaignStatus::Resuming => ['cancel'],
            default => ['duplicate'],
        }, 'primary' => match ($s) {
            WhatsAppCampaignStatus::Prepared => 'launch',
            WhatsAppCampaignStatus::Running => 'pause',
            WhatsAppCampaignStatus::Paused => 'resume',
            WhatsAppCampaignStatus::Ready => 'prepare',
            default => $s->editable() ? 'edit' : 'view',
        }, 'secondary' => 'view'];
    }
}
