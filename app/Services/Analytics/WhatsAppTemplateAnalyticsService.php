<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class WhatsAppTemplateAnalyticsService
{
    public function report(Tenant $t, AnalyticsDateRange $r, int $limit = 10): array
    {
        $templates = DB::table('whatsapp_message_templates')->where('tenant_id', $t->id);
        $statuses = (clone $templates)->select('status', DB::raw('COUNT(*) total'))->groupBy('status')->pluck('total', 'status');
        $usage = DB::table('whatsapp_message_template_usages')->where('tenant_id', $t->id)->whereBetween('created_at', [$r->from, $r->to]);
        $types = (clone $usage)->select('usage_type', DB::raw('COUNT(*) total'))->groupBy('usage_type')->pluck('total', 'usage_type');

        return ['metrics' => [AnalyticsMath::metric('total_templates', (clone $templates)->count()), AnalyticsMath::metric('draft', (int) ($statuses['draft'] ?? 0)), AnalyticsMath::metric('published', (int) ($statuses['published'] ?? 0)), AnalyticsMath::metric('archived', (int) ($statuses['archived'] ?? 0)), AnalyticsMath::metric('usage', (clone $usage)->count()), AnalyticsMath::metric('campaign_usage', (int) ($types['campaign'] ?? 0)), AnalyticsMath::metric('transactional_usage', (int) ($types['transactional'] ?? 0)), AnalyticsMath::metric('automation_usage', (int) ($types['automation'] ?? 0))], 'by_type' => (clone $templates)->select('type', DB::raw('COUNT(*) value'))->groupBy('type')->get(), 'most_used' => (clone $usage)->join('whatsapp_message_templates', 'whatsapp_message_templates.id', '=', 'whatsapp_message_template_usages.whatsapp_message_template_id')->where('whatsapp_message_templates.status', 'published')->select('whatsapp_message_templates.uuid', 'whatsapp_message_templates.name', DB::raw('COUNT(*) value'))->groupBy('whatsapp_message_templates.id', 'whatsapp_message_templates.uuid', 'whatsapp_message_templates.name')->orderByDesc('value')->limit(AnalyticsMath::limit($limit))->get()];
    }
}
