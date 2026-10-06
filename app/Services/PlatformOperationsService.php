<?php

namespace App\Services;

use App\Models\PlatformIncident;
use App\Services\Campaigns\WhatsAppCampaignConnectorHealthQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class PlatformOperationsService
{
    public function __construct(private SystemHealthService $health, private WhatsAppCampaignConnectorHealthQuery $connector) {}

    public function summary(): array
    {
        $stale = now()->subMinutes(config('operations.stale_minutes'));

        return [
            'health' => $this->health->checks(),
            'queues' => collect(config('operations.queues'))->mapWithKeys(fn (string $queue): array => [$queue => Queue::size($queue)])->all(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'connector' => $this->connector->get(),
            'active_sessions' => DB::table('whatsapp_sessions')->whereIn('status', ['ready', 'initializing', 'reconnecting'])->count(),
            'unhealthy_sessions' => DB::table('whatsapp_sessions')->whereIn('status', ['disconnected', 'failed'])->orWhere(fn ($query) => $query->where('status', 'ready')->where('last_seen_at', '<', $stale))->count(),
            'unknown_messages' => DB::table('whatsapp_messages')->where('failure_code', 'ambiguous_transport')->count(),
            'stuck_campaigns' => DB::table('whatsapp_campaign_executions')->whereIn('status', ['queued', 'running', 'processing'])->where(fn ($query) => $query->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $stale))->count(),
            'stuck_automations' => DB::table('automation_workflow_executions')->whereIn('status', ['pending', 'running'])->where(fn ($query) => $query->whereNull('last_heartbeat_at')->orWhere('last_heartbeat_at', '<', $stale))->count(),
            'overdue_automations' => DB::table('automation_workflow_executions')->where('status', 'waiting')->where('waiting_until', '<', now())->count(),
            'failed_webhooks' => DB::table('integration_webhook_deliveries')->where('status', 'failed')->count(),
            'inbox_failures' => DB::table('whatsapp_inbox_messages')->where('status', 'failed')->count(),
            'open_incidents' => PlatformIncident::query()->where('status', 'open')->count(),
            'recent_incidents' => PlatformIncident::query()->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")->latest('last_seen_at')->limit(20)->get(),
        ];
    }
}
