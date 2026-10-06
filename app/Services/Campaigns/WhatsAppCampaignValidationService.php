<?php

namespace App\Services\Campaigns;

use App\Contracts\TenantEntitlements;
use App\Data\Campaigns\CampaignValidationResult;
use App\Enums\WhatsAppCampaignSessionStrategy;
use App\Enums\WhatsAppMessageType;
use App\Enums\WhatsAppSessionStatus;
use App\Models\WhatsAppCampaign;

final class WhatsAppCampaignValidationService
{
    public function __construct(private TenantEntitlements $entitlements) {}

    public function validate(WhatsAppCampaign $c): CampaignValidationResult
    {
        $e = [];
        $w = ['Recipient audience has not yet been resolved.', 'Delivery and read acknowledgements are not guaranteed.'];
        try {
            $this->entitlements->requireFeature('campaigns.manage');
        } catch (\Throwable) {
            $e[] = 'Campaign access is not enabled for this subscription.';
        }
        if ($c->message_type === WhatsAppMessageType::Text && blank($c->body)) {
            $e[] = 'A text body is required.';
        }
        if ($c->message_type !== WhatsAppMessageType::Text && ! $c->attachment) {
            $e[] = 'A compatible private attachment is required.';
        }
        if ($c->audience_type->value !== 'all_eligible_contacts' && $c->audienceReferences()->count() === 0) {
            $e[] = 'An audience source is required.';
        }
        if ($c->session_strategy !== WhatsAppCampaignSessionStrategy::AutomaticPool) {
            if ($c->sessionSelections()->count() === 0) {
                $e[] = 'At least one tenant session is required.';
            } elseif ($c->sessionSelections()->whereHas('session', fn ($q) => $q->where('status', '!=', WhatsAppSessionStatus::Ready->value))->exists()) {
                $w[] = 'A selected session is currently disconnected.';
            }
        }

        return new CampaignValidationResult($e, $w);
    }
}
