<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Enums\TenantInvitationStatus;
use App\Models\AuditLog;
use App\Models\AutomationWorkflow;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\TenantMembership;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use Illuminate\Support\Collection;

final class TenantDashboardQuery
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantAuthorization $authorization,
    ) {}

    public function summary(): array
    {
        $tenant = $this->context->get();

        $subscription = $tenant->currentSubscription()->with('plan')->first();
        $sessionMetrics = WhatsAppSession::forTenant($tenant)
            ->selectRaw("COUNT(*) AS total_count, SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS ready_count")
            ->first();
        $conversationMetrics = WhatsAppConversation::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count, COALESCE(SUM(unread_count), 0) AS unread_count")
            ->first();

        return [
            'activeMembers' => TenantMembership::forTenant($tenant)->where('status', MembershipStatus::Active)->count(),
            'pendingInvitations' => Invitation::query()->where('tenant_id', $tenant->id)
                ->where('status', TenantInvitationStatus::Pending)->where('expires_at', '>', now())->count(),
            'customRoles' => Role::forTenant($tenant)->where('is_system', false)->count(),
            'whatsappSessions' => (int) ($sessionMetrics?->total_count ?? 0),
            'readyWhatsAppSessions' => (int) ($sessionMetrics?->ready_count ?? 0),
            'queuedMessages' => WhatsAppMessage::query()->where('tenant_id', $tenant->id)->whereIn('status', ['queued', 'processing', 'sending'])->count(),
            'messagesToday' => WhatsAppMessage::query()->where('tenant_id', $tenant->id)->where('created_at', '>=', now()->startOfDay())->count(),
            'openConversations' => (int) ($conversationMetrics?->open_count ?? 0),
            'unreadMessages' => (int) ($conversationMetrics?->unread_count ?? 0),
            'activeCampaigns' => WhatsAppCampaign::forTenant($tenant)->whereIn('status', ['ready', 'scheduled', 'running', 'paused'])->count(),
            'activeAutomations' => AutomationWorkflow::forTenant($tenant)->where('is_enabled', true)->where('status', '!=', 'archived')->count(),
            'subscription' => $subscription,
            'recentAuditLogs' => $this->authorization->allows('audit_logs.view')
                ? AuditLog::query()->where('tenant_id', $tenant->id)->with('user')->latest('created_at')->limit(6)->get()
                : new Collection,
        ];
    }
}
