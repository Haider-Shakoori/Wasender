<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\ArchiveAutomationWorkflowData;
use App\Data\Automations\AutomationWorkflowStepData;
use App\Data\Automations\CreateAutomationWorkflowData;
use App\Data\Automations\CreateAutomationWorkflowDraftVersionData;
use App\Data\Automations\EnableAutomationWorkflowData;
use App\Data\Automations\PublishAutomationWorkflowData;
use App\Enums\AutomationWorkflowStatus;
use App\Enums\AutomationWorkflowVersionStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Automations\ArchiveAutomationWorkflowService;
use App\Services\Automations\CreateAutomationWorkflowDraftVersionService;
use App\Services\Automations\CreateAutomationWorkflowService;
use App\Services\Automations\EnableAutomationWorkflowService;
use App\Services\Automations\PublishAutomationWorkflowService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AutomationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        $entitlements = $this->mock(TenantEntitlements::class);
        $entitlements->shouldReceive('requireFeature')->with('automations.access')->zeroOrMoreTimes();
        $entitlements->shouldReceive('requireCapacity')->with('automations.max')->zeroOrMoreTimes();
    }

    public function test_create_makes_version_one_draft_with_bounded_steps(): void
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create($this->definition(), $this->actor);
        $this->assertSame(AutomationWorkflowStatus::Draft, $workflow->status);
        $this->assertSame(1, $workflow->currentDraftVersion->version_number);
        $this->assertSame(AutomationWorkflowVersionStatus::Draft, $workflow->currentDraftVersion->status);
        $this->assertCount(1, $workflow->currentDraftVersion->steps);
        $this->assertNull($workflow->current_published_version_id);
    }

    public function test_publish_is_idempotent_and_published_definition_is_immutable(): void
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create($this->definition(), $this->actor);
        $published = app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);
        $again = app(PublishAutomationWorkflowService::class)->publish($workflow->refresh(), new PublishAutomationWorkflowData(2), $this->actor);
        $this->assertTrue($published->is($again));
        $this->assertNull($workflow->refresh()->current_draft_version_id);
        $this->expectException(ValidationException::class);
        $published->forceFill(['settings' => ['maximum_steps' => 2]])->save();
    }

    public function test_draft_from_published_is_copy_on_write_and_idempotent(): void
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create($this->definition(), $this->actor);
        app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);
        $service = app(CreateAutomationWorkflowDraftVersionService::class);
        $draft = $service->create($workflow->refresh(), new CreateAutomationWorkflowDraftVersionData(2), $this->actor);
        $same = $service->create($workflow->refresh(), new CreateAutomationWorkflowDraftVersionData(3), $this->actor);
        $this->assertTrue($draft->is($same));
        $this->assertSame(2, $draft->version_number);
        $this->assertCount(1, $draft->steps);
        $this->assertDatabaseCount('automation_workflow_versions', 2);
    }

    public function test_valid_manual_workflow_can_enable_and_archive_keeps_versions(): void
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create($this->definition(), $this->actor);
        try {
            app(EnableAutomationWorkflowService::class)->enable($workflow, new EnableAutomationWorkflowData(1), $this->actor);
            $this->fail('Draft workflow enabled.');
        } catch (ValidationException) {
            $this->assertFalse($workflow->refresh()->is_enabled);
        }
        app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);
        $enabled = app(EnableAutomationWorkflowService::class)->enable($workflow->refresh(), new EnableAutomationWorkflowData(2), $this->actor);
        $archived = app(ArchiveAutomationWorkflowService::class)->archive($enabled, new ArchiveAutomationWorkflowData(3), $this->actor);
        $this->assertFalse($archived->is_enabled);
        $this->assertSame(AutomationWorkflowStatus::Archived, $archived->status);
        $this->assertDatabaseCount('automation_workflow_versions', 1);
    }

    public function test_cross_tenant_draft_service_lookup_is_rejected(): void
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create($this->definition(), $this->actor);
        app(TenantContext::class)->set(Tenant::factory()->create());
        $this->expectException(ModelNotFoundException::class);
        app(CreateAutomationWorkflowDraftVersionService::class)->create($workflow, new CreateAutomationWorkflowDraftVersionData(1), $this->actor);
    }

    private function definition(): CreateAutomationWorkflowData
    {
        return new CreateAutomationWorkflowData('Welcome automation', null, 'manual', [], ['maximum_execution_minutes' => 60, 'maximum_steps' => 10, 'allow_reentry' => false, 'maximum_active_executions_per_contact' => 1], [new AutomationWorkflowStepData('stop', null, 'stop', 1, 'Finish', [])]);
    }
}
