<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class WhatsAppUsageAnalyticsService
{
    public function report(Tenant $t, AnalyticsDateRange $r): array
    {
        $usage = DB::table('subscription_usage_snapshots')->where('tenant_id', $t->id)->whereBetween('captured_at', [$r->from, $r->to]);
        $by = (clone $usage)->select('metric_key', DB::raw('MAX(value) value'))->groupBy('metric_key')->pluck('value', 'metric_key');
        $sub = DB::table('tenant_subscriptions')->where('tenant_id', $t->id)->where('is_current', true)->first();
        $limit = $sub ? DB::table('subscription_plan_features')->where('plan_id', $sub->plan_id)->where('feature_key', 'messages.monthly')->first() : null;
        $messages = (int) ($by['messages.monthly'] ?? 0);
        $capacity = $limit && ! $limit->is_unlimited ? (int) $limit->integer_value : null;

        return ['metrics' => [AnalyticsMath::metric('message_usage', $messages), AnalyticsMath::metric('campaign_usage', (int) ($by['campaigns.monthly_max'] ?? 0)), AnalyticsMath::metric('automation_usage', (int) ($by['automations.executions'] ?? 0)), AnalyticsMath::metric('message_limit', $capacity ?? 0), AnalyticsMath::metric('message_remaining', $capacity === null ? 0 : max(0, $capacity - $messages))], 'unlimited_messages' => (bool) ($limit?->is_unlimited ?? false), 'over_time' => (clone $usage)->selectRaw('DATE(captured_at) period, MAX(value) value')->where('metric_key', 'messages.monthly')->groupByRaw('DATE(captured_at)')->orderBy('period')->get(), 'by_source' => ['transactional' => DB::table('whatsapp_messages')->where('tenant_id', $t->id)->whereBetween('created_at', [$r->from, $r->to])->whereNull('deleted_at')->count(), 'campaign' => (int) DB::table('whatsapp_campaigns')->where('tenant_id', $t->id)->whereBetween('created_at', [$r->from, $r->to])->sum('sent_recipient_count'), 'automation' => DB::table('whatsapp_message_template_usages')->where('tenant_id', $t->id)->where('usage_type', 'automation')->whereBetween('created_at', [$r->from, $r->to])->count(), 'inbox_agent_reply' => DB::table('whatsapp_inbox_messages')->where('tenant_id', $t->id)->where('direction', 'outbound')->whereBetween('occurred_at', [$r->from, $r->to])->count()]];
    }
}
