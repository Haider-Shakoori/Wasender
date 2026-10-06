<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantEntitlements;
use App\Models\Tenant;
use App\Models\WhatsAppCampaignRecipientExecution;
use App\Models\WhatsAppCampaignUsageReservation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CampaignUsageReservationService
{
    public function __construct(private TenantEntitlements $entitlements) {}

    public function reserve(Tenant $tenant, int $units, string $key): WhatsAppCampaignUsageReservation
    {
        $existing = WhatsAppCampaignUsageReservation::where('tenant_id', $tenant->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }
        $remaining = $this->entitlements->remaining('messages.monthly');
        if ($remaining !== null && $units > $remaining) {
            throw ValidationException::withMessages(['usage' => 'Monthly message capacity is insufficient for this campaign.']);
        }
        $r = new WhatsAppCampaignUsageReservation;
        $r->forceFill(['tenant_id' => $tenant->id, 'status' => 'reserved', 'reserved_units' => $units, 'consumed_units' => 0, 'released_units' => 0, 'idempotency_key' => $key, 'expires_at' => now()->addHours(config('whatsapp_campaign_execution.max_runtime_hours'))])->save();

        return $r;
    }

    public function release(WhatsAppCampaignUsageReservation $r, int $units): void
    {
        DB::transaction(function () use ($r, $units): void {
            $locked = WhatsAppCampaignUsageReservation::whereKey($r->id)->lockForUpdate()->firstOrFail();
            $release = min($units, $locked->available());
            $locked->forceFill(['released_units' => $locked->released_units + $release, 'status' => $locked->available() - $release === 0 ? 'released' : 'reserved'])->save();
            $r->setRawAttributes($locked->getAttributes(), true);
        }, 3);
    }

    public function consumeForRecipient(WhatsAppCampaignRecipientExecution $recipient): bool
    {
        if ($recipient->usage_consumed_at) {
            return false;
        }
        $execution = $recipient->execution;
        if (! $execution->usage_reservation_id) {
            return false;
        }
        $reservation = WhatsAppCampaignUsageReservation::whereKey($execution->usage_reservation_id)->lockForUpdate()->firstOrFail();
        $recipient = WhatsAppCampaignRecipientExecution::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
        if ($recipient->usage_consumed_at) {
            return false;
        }
        if ($reservation->available() < 1) {
            throw new \RuntimeException('Campaign usage reservation is exhausted.');
        }
        $reservation->forceFill([
            'consumed_units' => DB::raw('consumed_units + 1'),
            'status' => $reservation->available() === 1 ? 'consumed' : 'reserved',
        ])->save();
        $recipient->forceFill(['usage_consumed_at' => now()])->save();

        return true;
    }
}
