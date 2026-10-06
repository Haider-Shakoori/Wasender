<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class WhatsAppCampaignAnalyticsService
{
    public function report(Tenant $t, AnalyticsDateRange $r, int $limit = 10): array
    {
        $q = DB::table('whatsapp_campaigns')->where('tenant_id', $t->id)->whereNull('deleted_at')->whereBetween('created_at', [$r->from, $r->to]);
        $status = (clone $q)->select('status', DB::raw('COUNT(*) total'))->groupBy('status')->pluck('total', 'status');
        $sum = (clone $q)->selectRaw('COALESCE(SUM(eligible_recipient_count),0) prepared, COALESCE(SUM(sent_recipient_count),0) sent, COALESCE(SUM(delivered_recipient_count),0) delivered, COALESCE(SUM(read_recipient_count),0) `read`, COALESCE(SUM(failed_recipient_count),0) failed, COALESCE(SUM(skipped_recipient_count),0) skipped, COALESCE(SUM(excluded_recipient_count),0) excluded')->first();

        return ['metrics' => [AnalyticsMath::metric('campaigns', (clone $q)->count()), ...collect($status)->map(fn ($v, $k) => AnalyticsMath::metric($k, (int) $v))->values()->all(), AnalyticsMath::metric('prepared', (int) $sum->prepared), AnalyticsMath::metric('sent', (int) $sum->sent), AnalyticsMath::metric('delivered', (int) $sum->delivered), AnalyticsMath::metric('read', (int) $sum->read), AnalyticsMath::metric('failed', (int) $sum->failed), AnalyticsMath::metric('skipped', (int) $sum->skipped), AnalyticsMath::metric('excluded', (int) $sum->excluded), AnalyticsMath::metric('delivery_rate', AnalyticsMath::rate($sum->delivered, $sum->sent) ?? 0), AnalyticsMath::metric('read_rate', AnalyticsMath::rate($sum->read, $sum->sent) ?? 0), AnalyticsMath::metric('failure_rate', AnalyticsMath::rate($sum->failed, $sum->sent + $sum->failed) ?? 0)], 'over_time' => (clone $q)->selectRaw('DATE(created_at) period, SUM(sent_recipient_count) value')->groupByRaw('DATE(created_at)')->orderBy('period')->get(), 'top' => (clone $q)->select('uuid', 'name', 'sent_recipient_count', 'delivered_recipient_count', 'read_recipient_count', 'failed_recipient_count')->orderByDesc('sent_recipient_count')->limit(AnalyticsMath::limit($limit))->get()];
    }
}
