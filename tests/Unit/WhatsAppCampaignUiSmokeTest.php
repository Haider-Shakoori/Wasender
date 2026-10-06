<?php

namespace Tests\Unit;

use App\Enums\WhatsAppCampaignStatus;
use App\Services\Campaigns\WhatsAppCampaignFailurePresenter;
use App\Services\Campaigns\WhatsAppCampaignStatusPresenter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class WhatsAppCampaignUiSmokeTest extends TestCase
{
    public function test_essential_tenant_and_platform_routes_are_registered(): void
    {
        foreach ([
            'tenant.campaigns.index', 'tenant.campaigns.create', 'tenant.campaigns.show', 'tenant.campaigns.status',
            'tenant.campaigns.recipients', 'tenant.campaigns.exclusions', 'tenant.campaigns.execution.recipients',
            'tenant.campaigns.execution.attempts', 'tenant.campaigns.timeline', 'platform.campaigns.index',
            'platform.campaign-preparations.index', 'platform.campaign-executions.index',
            'platform.campaign-attempts.index', 'platform.campaign-connector.show',
        ] as $name) {
            $this->assertNotNull(Route::getRoutes()->getByName($name), $name);
        }
    }

    public function test_lifecycle_presenter_exposes_actions_without_making_running_campaigns_editable(): void
    {
        $presenter = app(WhatsAppCampaignStatusPresenter::class);
        $this->assertContains('launch', $presenter->present(WhatsAppCampaignStatus::Prepared)['actions']);
        $this->assertContains('pause', $presenter->present(WhatsAppCampaignStatus::Running)['actions']);
        $this->assertContains('cancel', $presenter->present(WhatsAppCampaignStatus::Running)['actions']);
        $this->assertNotContains('edit', $presenter->present(WhatsAppCampaignStatus::Running)['actions']);
    }

    public function test_failure_presenter_returns_safe_guidance(): void
    {
        $failure = app(WhatsAppCampaignFailurePresenter::class)->present('transport_state_unknown');
        $this->assertSame('Transport result uncertain', $failure['title']);
        $this->assertStringNotContainsString('HMAC', implode(' ', $failure));
        $this->assertFalse($failure['retry']);
    }

    public function test_platform_attempt_view_contains_no_sensitive_transport_fields(): void
    {
        $view = file_get_contents(resource_path('views/platform/campaign-attempts/index.blade.php'));
        foreach (['phone_normalized', 'retrieval_token', 'storage_key', 'X-Internal-Signature', 'message.body'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $view);
        }
    }
}
