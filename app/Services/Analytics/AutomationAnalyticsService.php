<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class AutomationAnalyticsService
{
    public function report(Tenant $t, AnalyticsDateRange $r): array
    {
        $flows = DB::table('automation_workflows')->where('tenant_id', $t->id)->whereNull('deleted_at');
        $exec = DB::table('automation_workflow_executions')->where('tenant_id', $t->id)->whereBetween('created_at', [$r->from, $r->to]);
        $statuses = (clone $exec)->select('status', DB::raw('COUNT(*) total'))->groupBy('status')->pluck('total', 'status');
        $total = (clone $exec)->count();
        $completed = (int) ($statuses['completed'] ?? 0);
        $failed = (int) ($statuses['failed'] ?? 0);

        return ['metrics' => [AnalyticsMath::metric('total_workflows', (clone $flows)->count()), AnalyticsMath::metric('enabled', (clone $flows)->where('is_enabled', true)->count()), AnalyticsMath::metric('disabled', (clone $flows)->where('is_enabled', false)->count()), AnalyticsMath::metric('executions', $total), AnalyticsMath::metric('completed', $completed), AnalyticsMath::metric('failed', $failed), AnalyticsMath::metric('cancelled', (int) ($statuses['cancelled'] ?? 0)), AnalyticsMath::metric('waiting', (int) ($statuses['waiting'] ?? 0)), AnalyticsMath::metric('timed_out', (int) ($statuses['timed_out'] ?? 0)), AnalyticsMath::metric('success_rate', AnalyticsMath::rate($completed, $total) ?? 0), AnalyticsMath::metric('failure_rate', AnalyticsMath::rate($failed, $total) ?? 0), AnalyticsMath::metric('actions_processed', (int) (clone $exec)->sum('processed_steps'))], 'over_time' => (clone $exec)->selectRaw('DATE(created_at) period, COUNT(*) value')->groupByRaw('DATE(created_at)')->orderBy('period')->get()];
    }
}
