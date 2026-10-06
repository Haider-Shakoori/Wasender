<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PlatformWhatsAppAnalyticsService
{
    public function report(AnalyticsDateRange $r, ?string $tenantUuid = null): array
    {
        if ($tenantUuid && ! DB::table('tenants')->where('uuid', $tenantUuid)->exists()) {
            abort(404);
        }
        $key = 'analytics:platform:overview:'.$r->hash().':'.hash('sha256', $tenantUuid ?? 'all');

        return Cache::remember($key, 60, function () use ($r, $tenantUuid) {
            $tenantId = $tenantUuid ? DB::table('tenants')->where('uuid', $tenantUuid)->value('id') : null;
            $scope = fn ($q) => $q->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId));
            $messages = $scope(DB::table('whatsapp_messages'))->whereBetween('created_at', [$r->from, $r->to]);

            return ['metrics' => [AnalyticsMath::metric('total_tenants', DB::table('tenants')->when($tenantId, fn ($q) => $q->where('id', $tenantId))->count()), AnalyticsMath::metric('active_tenants', DB::table('tenants')->when($tenantId, fn ($q) => $q->where('id', $tenantId))->where('is_active', true)->count()), AnalyticsMath::metric('sessions', $scope(DB::table('whatsapp_sessions'))->whereNull('deleted_at')->count()), AnalyticsMath::metric('connected_sessions', $scope(DB::table('whatsapp_sessions'))->whereNull('deleted_at')->where('status', 'ready')->count()), AnalyticsMath::metric('messages', (clone $messages)->count()), AnalyticsMath::metric('failed_messages', (clone $messages)->where('status', 'failed')->count()), AnalyticsMath::metric('campaigns', $scope(DB::table('whatsapp_campaigns'))->whereBetween('created_at', [$r->from, $r->to])->count()), AnalyticsMath::metric('automations', $scope(DB::table('automation_workflows'))->count()), AnalyticsMath::metric('automation_executions', $scope(DB::table('automation_workflow_executions'))->whereBetween('created_at', [$r->from, $r->to])->count()), AnalyticsMath::metric('inbox_conversations', $scope(DB::table('whatsapp_conversations'))->count()), AnalyticsMath::metric('unhealthy_sessions', $scope(DB::table('whatsapp_sessions'))->whereIn('status', ['failed', 'reconnecting', 'disconnected'])->count()), AnalyticsMath::metric('stuck_workflows', $scope(DB::table('automation_workflow_executions'))->whereIn('status', ['running', 'waiting'])->where('last_heartbeat_at', '<', now()->subMinutes(15))->count()), AnalyticsMath::metric('failed_campaign_executions', $scope(DB::table('whatsapp_campaign_executions'))->where('status', 'failed')->whereBetween('created_at', [$r->from, $r->to])->count()), AnalyticsMath::metric('inbox_failures', $scope(DB::table('whatsapp_inbox_messages'))->where('status', 'failed')->whereBetween('occurred_at', [$r->from, $r->to])->count())], 'message_volume' => (clone $messages)->selectRaw('DATE(created_at) period, COUNT(*) value')->groupByRaw('DATE(created_at)')->orderBy('period')->get(), 'tenant_usage' => DB::table('subscription_usage_snapshots')->join('tenants', 'tenants.id', '=', 'subscription_usage_snapshots.tenant_id')->when($tenantId, fn ($q) => $q->where('tenants.id', $tenantId))->whereBetween('captured_at', [$r->from, $r->to])->where('metric_key', 'messages.monthly')->select('tenants.uuid', 'tenants.name', DB::raw('MAX(value) value'))->groupBy('tenants.id', 'tenants.uuid', 'tenants.name')->orderByDesc('value')->limit(50)->get()];
        });
    }
}
