<?php

namespace App\Services\Campaigns;

use App\Enums\WhatsAppCampaignEvent;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignEventRecord;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhatsAppCampaignLifecycleService
{
    private const ALLOWED = ['draft' => ['validating', 'needs_attention', 'ready', 'archived'], 'validating' => ['ready', 'needs_attention', 'draft'], 'needs_attention' => ['draft', 'validating', 'ready', 'archived'], 'ready' => ['draft', 'scheduled', 'preparing', 'archived'], 'scheduled' => ['ready', 'preparing'], 'preparing' => ['prepared', 'needs_attention', 'failed', 'ready'], 'prepared' => ['ready', 'queued', 'needs_attention', 'archived'], 'queued' => ['running', 'paused', 'cancelling', 'cancelled', 'failed'], 'running' => ['pausing', 'cancelling', 'completed', 'completed_with_errors', 'failed'], 'pausing' => ['paused', 'cancelling', 'failed'], 'paused' => ['resuming', 'cancelling', 'cancelled', 'failed'], 'resuming' => ['running', 'pausing', 'cancelling', 'failed'], 'cancelling' => ['cancelled', 'completed_with_errors', 'failed'], 'failed' => ['archived'], 'cancelled' => ['archived'], 'completed' => ['archived'], 'completed_with_errors' => ['archived']];

    public function __construct(private AuditService $audit) {}

    public function transition(WhatsAppCampaign $c, WhatsAppCampaignStatus $to, WhatsAppCampaignEvent $event, ?User $actor = null, ?int $expectedVersion = null, array $metadata = []): WhatsAppCampaign
    {
        return DB::transaction(function () use ($c, $to, $event, $actor, $expectedVersion, $metadata) {
            $locked = WhatsAppCampaign::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if ($expectedVersion !== null && $locked->version !== $expectedVersion) {
                throw ValidationException::withMessages(['expected_version' => 'This campaign changed in another request. Refresh and try again.']);
            } $from = $locked->status;
            if ($from === $to) {
                return $locked;
            } if (! in_array($to->value, self::ALLOWED[$from->value] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Invalid campaign transition: {$from->value} to {$to->value}."]);
            } $locked->forceFill(['status' => $to, 'archived_at' => $to === WhatsAppCampaignStatus::Archived ? now() : $locked->archived_at, 'updated_by' => $actor?->id])->save();
            WhatsAppCampaignEventRecord::create(['tenant_id' => $locked->tenant_id, 'whatsapp_campaign_id' => $locked->id, 'event' => $event->value, 'from_status' => $from->value, 'to_status' => $to->value, 'source' => 'user', 'actor_type' => $actor?->getMorphClass(), 'actor_id' => $actor?->id, 'metadata' => $metadata, 'occurred_at' => now()]);
            $this->audit->recordDomain('whatsapp_campaign.'.str($event->value)->after('.'), $actor, $locked->tenant, $locked, ['campaign_uuid' => $locked->uuid, 'previous_status' => $from->value, 'new_status' => $to->value, 'version' => $locked->version]);

            return $locked->refresh();
        });
    }
}
