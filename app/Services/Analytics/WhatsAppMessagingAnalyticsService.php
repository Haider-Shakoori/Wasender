<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class WhatsAppMessagingAnalyticsService
{
    public function report(Tenant $tenant, AnalyticsDateRange $range, int $limit = 10, ?string $sessionUuid = null): array
    {
        $out = DB::table('whatsapp_messages')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereBetween('created_at', [$range->from, $range->to]);
        $in = DB::table('whatsapp_inbox_messages')->where('tenant_id', $tenant->id)->where('direction', 'inbound')->whereBetween('occurred_at', [$range->from, $range->to]);
        if ($sessionUuid) {
            $sessionId = DB::table('whatsapp_sessions')->where('tenant_id', $tenant->id)->where('uuid', $sessionUuid)->value('id');
            $out->where('whatsapp_session_id', $sessionId);
            $in->where('whatsapp_session_id', $sessionId);
        }
        $statuses = (clone $out)->select('status', DB::raw('COUNT(*) total'))->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $outbound = (clone $out)->count();
        $inbound = (clone $in)->count();
        $sent = ($statuses['sent'] ?? 0) + ($statuses['delivered'] ?? 0) + ($statuses['read'] ?? 0);
        $delivered = ($statuses['delivered'] ?? 0) + ($statuses['read'] ?? 0);
        $read = $statuses['read'] ?? 0;
        $failed = $statuses['failed'] ?? 0;
        $previousRange = $range->previous();
        $previous = DB::table('whatsapp_messages')->where('tenant_id', $tenant->id)->whereNull('deleted_at')->whereBetween('created_at', [$previousRange->from, $previousRange->to])->count()
            + DB::table('whatsapp_inbox_messages')->where('tenant_id', $tenant->id)->where('direction', 'inbound')->whereBetween('occurred_at', [$previousRange->from, $previousRange->to])->count();

        return ['metrics' => [AnalyticsMath::metric('total_messages', $outbound + $inbound, $previous), AnalyticsMath::metric('outbound_messages', $outbound), AnalyticsMath::metric('inbound_messages', $inbound), AnalyticsMath::metric('queued', $statuses['queued'] ?? 0), AnalyticsMath::metric('sent', $sent), AnalyticsMath::metric('delivered', $delivered), AnalyticsMath::metric('read', $read), AnalyticsMath::metric('failed', $failed), AnalyticsMath::metric('delivery_rate', AnalyticsMath::rate($delivered, $sent) ?? 0), AnalyticsMath::metric('read_rate', AnalyticsMath::rate($read, $sent) ?? 0), AnalyticsMath::metric('failure_rate', AnalyticsMath::rate($failed, $outbound) ?? 0)],
            'by_type' => (clone $out)->select('message_type', DB::raw('COUNT(*) value'))->groupBy('message_type')->orderByDesc('value')->limit(AnalyticsMath::limit($limit))->get(), 'by_session' => (clone $out)->join('whatsapp_sessions', 'whatsapp_sessions.id', '=', 'whatsapp_messages.whatsapp_session_id')->select('whatsapp_sessions.uuid', 'whatsapp_sessions.name', DB::raw('COUNT(*) value'))->groupBy('whatsapp_sessions.id', 'whatsapp_sessions.uuid', 'whatsapp_sessions.name')->orderByDesc('value')->limit(AnalyticsMath::limit($limit))->get(), 'over_time' => (clone $out)->selectRaw('DATE(created_at) period, COUNT(*) value')->groupByRaw('DATE(created_at)')->orderBy('period')->get(), 'inbound_over_time' => (clone $in)->selectRaw('DATE(occurred_at) period, COUNT(*) value')->groupByRaw('DATE(occurred_at)')->orderBy('period')->get()];
    }
}
