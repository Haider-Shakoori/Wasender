<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TenantAuthorization;
use App\Contracts\TenantContext;
use App\Enums\MembershipStatus;
use App\Enums\TenantInvitationStatus;
use App\Models\AuditLog;
use App\Models\AutomationWorkflow;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $workspaceMetrics = DB::table('tenants')
            ->where('tenants.id', $tenant->id)
            ->selectSub(
                fn ($query) => $query->from('tenant_memberships')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tenant_memberships.tenant_id', 'tenants.id')
                    ->where('status', MembershipStatus::Active->value),
                'active_members',
            )
            ->selectSub(
                fn ($query) => $query->from('invitations')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('invitations.tenant_id', 'tenants.id')
                    ->where('status', TenantInvitationStatus::Pending->value)
                    ->where('expires_at', '>', now()),
                'pending_invitations',
            )
            ->selectSub(
                fn ($query) => $query->from('roles')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('roles.tenant_id', 'tenants.id')
                    ->where('is_system', false),
                'custom_roles',
            )
            ->first();
        $sessionMetrics = WhatsAppSession::forTenant($tenant)
            ->selectRaw("COUNT(*) AS total_count, SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS ready_count")
            ->first();
        $conversationMetrics = WhatsAppConversation::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw("SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count, COALESCE(SUM(unread_count), 0) AS unread_count")
            ->first();
        $messageMetrics = WhatsAppMessage::query()
            ->where('tenant_id', $tenant->id)
            ->selectRaw("SUM(CASE WHEN status IN ('queued', 'processing', 'sending') THEN 1 ELSE 0 END) AS queued_count, SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS today_count", [now()->startOfDay()])
            ->first();

        return [
            'activeMembers' => (int) ($workspaceMetrics?->active_members ?? 0),
            'pendingInvitations' => (int) ($workspaceMetrics?->pending_invitations ?? 0),
            'customRoles' => (int) ($workspaceMetrics?->custom_roles ?? 0),
            'whatsappSessions' => (int) ($sessionMetrics?->total_count ?? 0),
            'readyWhatsAppSessions' => (int) ($sessionMetrics?->ready_count ?? 0),
            'queuedMessages' => (int) ($messageMetrics?->queued_count ?? 0),
            'messagesToday' => (int) ($messageMetrics?->today_count ?? 0),
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
