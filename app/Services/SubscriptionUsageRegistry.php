<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\TenantInvitationStatus;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\ContactSegment;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppSession;
use Illuminate\Support\Carbon;

final class SubscriptionUsageRegistry
{
    public function usage(Tenant $tenant, string $key): int
    {
        return match ($key) {
            'team_members.max' => TenantMembership::where('tenant_id', $tenant->id)->where('status', MembershipStatus::Active)->count(),
            'pending_invitations.max' => Invitation::where('tenant_id', $tenant->id)->where('status', TenantInvitationStatus::Pending)->where('expires_at', '>', now())->count(),
            'roles.custom.max' => Role::where('tenant_id', $tenant->id)->where('is_system', false)->count(),
            'whatsapp_sessions.max' => WhatsAppSession::forTenant($tenant)->capacityConsuming()->count(),
            'messages.monthly' => $this->messageUsage($tenant),
            'contacts.max' => Contact::where('tenant_id', $tenant->id)->count(),
            'contact_groups.max' => ContactGroup::where('tenant_id', $tenant->id)->count(),
            'contact_labels.max' => ContactLabel::where('tenant_id', $tenant->id)->count(),
            'contact_segments.max' => ContactSegment::where('tenant_id', $tenant->id)->count(),
            'campaigns.max' => WhatsAppCampaign::forTenant($tenant)->where('status', '!=', 'archived')->count(),
            'campaigns.active_max' => WhatsAppCampaign::forTenant($tenant)->whereIn('status', ['draft', 'validating', 'needs_attention', 'ready', 'scheduled'])->count(),
            'campaigns.monthly_max' => WhatsAppCampaign::forTenant($tenant)->whereNotNull('launch_requested_at')->where('launch_requested_at', '>=', now()->startOfMonth())->count(),
            'whatsapp_message_templates.max' => WhatsAppMessageTemplate::forTenant($tenant)->where('status', '!=', 'archived')->count(),
            'whatsapp_message_templates.published_max' => WhatsAppMessageTemplate::forTenant($tenant)->whereNotNull('current_published_version_id')->count(),
            'automations.max' => AutomationWorkflow::forTenant($tenant)->where('status', '!=', 'archived')->count(),
            default => 0
        };
    }

    public function measurableKeys(): array
    {
        return ['team_members.max', 'pending_invitations.max', 'roles.custom.max', 'whatsapp_sessions.max', 'messages.monthly', 'contacts.max', 'contact_groups.max', 'contact_labels.max', 'contact_segments.max', 'campaigns.max', 'campaigns.active_max', 'campaigns.monthly_max', 'whatsapp_message_templates.max', 'whatsapp_message_templates.published_max', 'automations.max'];
    }

    public function summary(Tenant $tenant, string $key): array
    {
        $usage = $this->usage($tenant, $key);
        $feature = $tenant->currentSubscription?->plan?->features?->firstWhere('feature_key', $key);
        $unlimited = (bool) $feature?->is_unlimited;
        $limit = $unlimited ? null : (int) ($feature?->integer_value ?? 0);

        return [
            'usage' => $usage,
            'limit' => $limit,
            'unlimited' => $unlimited,
            'remaining' => $unlimited ? null : max(0, $limit - $usage),
            'percentage' => $unlimited ? 0 : ($limit > 0 ? min(100, round(($usage / $limit) * 100, 1)) : 100),
        ];
    }

    private function messageUsage(Tenant $tenant): int
    {
        $subscription = $tenant->currentSubscription;
        $start = $subscription?->current_period_starts_at ?? Carbon::now()->startOfMonth();
        $end = $subscription?->current_period_ends_at;

        return WhatsAppMessage::where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $start)
            ->when($end, fn ($query) => $query->where('created_at', '<', $end))
            ->count();
    }
}
