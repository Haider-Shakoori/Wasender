<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

final class AnalyticsOverviewService
{
    public function __construct(private WhatsAppMessagingAnalyticsService $messages, private WhatsAppSessionAnalyticsService $sessions, private WhatsAppCampaignAnalyticsService $campaigns, private WhatsAppTemplateAnalyticsService $templates, private AutomationAnalyticsService $automations, private WhatsAppInboxAnalyticsService $inbox, private WhatsAppUsageAnalyticsService $usage) {}

    public function report(Tenant $tenant, AnalyticsDateRange $range, int $limit = 10, ?string $sessionUuid = null): array
    {
        $key = 'analytics:'.$tenant->uuid.':overview:'.$range->hash().':'.AnalyticsMath::limit($limit).':'.hash('sha256', $sessionUuid ?? 'all');

        return Cache::remember($key, 120, fn () => [
            'range' => ['from' => $range->from->toIso8601String(), 'to' => $range->to->toIso8601String(), 'timezone' => $range->timezone],
            'messages' => $this->messages->report($tenant, $range, $limit, $sessionUuid), 'sessions' => $this->sessions->report($tenant, $range, $limit),
            'campaigns' => $this->campaigns->report($tenant, $range, $limit), 'templates' => $this->templates->report($tenant, $range, $limit),
            'automations' => $this->automations->report($tenant, $range), 'inbox' => $this->inbox->report($tenant, $range, $limit), 'usage' => $this->usage->report($tenant, $range),
        ]);
    }
}
