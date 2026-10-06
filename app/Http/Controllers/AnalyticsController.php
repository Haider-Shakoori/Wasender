<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Analytics\AnalyticsDateRange;
use App\Http\Requests\AnalyticsFilterRequest;
use App\Services\Analytics\AnalyticsOverviewService;
use App\Services\Analytics\AutomationAnalyticsService;
use App\Services\Analytics\WhatsAppCampaignAnalyticsService;
use App\Services\Analytics\WhatsAppInboxAnalyticsService;
use App\Services\Analytics\WhatsAppMessagingAnalyticsService;
use App\Services\Analytics\WhatsAppSessionAnalyticsService;
use App\Services\Analytics\WhatsAppUsageAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class AnalyticsController extends Controller
{
    public function overview(AnalyticsFilterRequest $r, TenantContext $c, AnalyticsOverviewService $s): JsonResponse
    {
        [$t,$range,$limit] = $this->context($r, $c);

        return response()->json($s->report($t, $range, $limit));
    }

    public function messages(AnalyticsFilterRequest $r, TenantContext $c, WhatsAppMessagingAnalyticsService $s): JsonResponse
    {
        [$t,$range,$limit] = $this->context($r, $c);

        return response()->json($s->report($t, $range, $limit));
    }

    public function campaigns(AnalyticsFilterRequest $r, TenantContext $c, WhatsAppCampaignAnalyticsService $s): JsonResponse
    {
        [$t,$range,$limit] = $this->context($r, $c);

        return response()->json($s->report($t, $range, $limit));
    }

    public function automations(AnalyticsFilterRequest $r, TenantContext $c, AutomationAnalyticsService $s): JsonResponse
    {
        [$t,$range] = $this->context($r, $c);

        return response()->json($s->report($t, $range));
    }

    public function inbox(AnalyticsFilterRequest $r, TenantContext $c, WhatsAppInboxAnalyticsService $s): JsonResponse
    {
        [$t,$range,$limit] = $this->context($r, $c);

        return response()->json($s->report($t, $range, $limit));
    }

    public function sessions(AnalyticsFilterRequest $r, TenantContext $c, WhatsAppSessionAnalyticsService $s): JsonResponse
    {
        [$t,$range,$limit] = $this->context($r, $c);

        return response()->json($s->report($t, $range, $limit));
    }

    public function usage(AnalyticsFilterRequest $r, TenantContext $c, WhatsAppUsageAnalyticsService $s): JsonResponse
    {
        [$t,$range] = $this->context($r, $c);

        return response()->json($s->report($t, $range));
    }

    private function context(AnalyticsFilterRequest $r, TenantContext $c): array
    {
        $t = $c->get();
        foreach (['session_uuid' => 'whatsapp_sessions', 'campaign_uuid' => 'whatsapp_campaigns', 'workflow_uuid' => 'automation_workflows', 'user_uuid' => 'users'] as $field => $table) {
            if ($uuid = $r->validated($field)) {
                $q = DB::table($table)->where('uuid', $uuid);
                if ($table === 'users') {
                    $q->whereExists(fn ($q) => $q->selectRaw('1')->from('tenant_user')->whereColumn('tenant_user.user_id', 'users.id')->where('tenant_user.tenant_id', $t->id));
                } else {
                    $q->where('tenant_id', $t->id);
                }abort_unless($q->exists(), 422);
            }
        }

        return [$t, AnalyticsDateRange::fromFilters($r->validated(), $t->timezone), min(50, max(1, (int) $r->validated('limit', 10)))];
    }
}
