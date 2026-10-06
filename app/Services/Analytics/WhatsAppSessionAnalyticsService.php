<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class WhatsAppSessionAnalyticsService
{
    public function report(Tenant $t, AnalyticsDateRange $r, int $limit = 10): array
    {
        $q = DB::table('whatsapp_sessions')->where('tenant_id', $t->id)->whereNull('deleted_at');
        $statuses = (clone $q)->select('status', DB::raw('COUNT(*) total'))->groupBy('status')->pluck('total', 'status');
        $by = (clone $q)->leftJoin('whatsapp_messages', function ($j) use ($r) {
            $j->on('whatsapp_messages.whatsapp_session_id', '=', 'whatsapp_sessions.id')->whereBetween('whatsapp_messages.created_at', [$r->from, $r->to])->whereNull('whatsapp_messages.deleted_at');
        })->select('whatsapp_sessions.uuid', 'whatsapp_sessions.name', 'whatsapp_sessions.status', 'whatsapp_sessions.last_seen_at', DB::raw('COUNT(whatsapp_messages.id) messages'), DB::raw("SUM(CASE WHEN whatsapp_messages.status IN ('sent','delivered','read') THEN 1 ELSE 0 END) sent"), DB::raw("SUM(CASE WHEN whatsapp_messages.status IN ('delivered','read') THEN 1 ELSE 0 END) delivered"), DB::raw("SUM(CASE WHEN whatsapp_messages.status='failed' THEN 1 ELSE 0 END) failed"))->groupBy('whatsapp_sessions.id', 'whatsapp_sessions.uuid', 'whatsapp_sessions.name', 'whatsapp_sessions.status', 'whatsapp_sessions.last_seen_at')->orderByDesc('messages')->limit(AnalyticsMath::limit($limit))->get();

        return ['metrics' => [AnalyticsMath::metric('total_sessions', (clone $q)->count()), AnalyticsMath::metric('connected', (int) ($statuses['ready'] ?? 0)), AnalyticsMath::metric('disconnected', (int) ($statuses['disconnected'] ?? 0)), AnalyticsMath::metric('restricted', 0), AnalyticsMath::metric('unhealthy', (int) (($statuses['failed'] ?? 0) + ($statuses['reconnecting'] ?? 0)))], 'by_session' => $by];
    }
}
