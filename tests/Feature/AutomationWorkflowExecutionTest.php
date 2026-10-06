<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\AutomationWorkflowExecutionContext;
use App\Data\Automations\AutomationWorkflowStepData;
use App\Data\Automations\CreateAutomationWorkflowData;
use App\Data\Automations\EnableAutomationWorkflowData;
use App\Data\Automations\PublishAutomationWorkflowData;
use App\Data\Automations\StartAutomationWorkflowData;
use App\Enums\AutomationWorkflowExecutionStatus;
use App\Jobs\ResumeAutomationWorkflowDelay;
use App\Jobs\StartAutomationWorkflowExecution;
use App\Models\AutomationWorkflowExecution;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Automations\CancelAutomationWorkflowExecutionService;
use App\Services\Automations\CreateAutomationWorkflowService;
use App\Services\Automations\EnableAutomationWorkflowService;
use App\Services\Automations\ProcessAutomationWorkflowStepService;
use App\Services\Automations\PublishAutomationWorkflowService;
use App\Services\Automations\StartAutomationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AutomationWorkflowExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create();
        app(TenantContext::class)->set($this->tenant);
        $entitlements = $this->mock(TenantEntitlements::class);
        $entitlements->shouldReceive('requireFeature')->zeroOrMoreTimes();
        $entitlements->shouldReceive('requireCapacity')->zeroOrMoreTimes();
    }

    public function test_disabled_workflow_cannot_start(): void
    {
        $workflow = $this->published($this->stops(), false);
        $this->expectException(ValidationException::class);
        $this->start($workflow, 'disabled');
    }

    public function test_duplicate_start_is_idempotent_and_binds_version_hash(): void
    {
        $workflow = $this->published($this->stops());
        $first = $this->start($workflow, 'same-key');
        $second = $this->start($workflow, 'same-key');
        $this->assertTrue($first->is($second));
        $this->assertSame($workflow->current_published_version_id, $first->automation_workflow_version_id);
        $this->assertSame($workflow->currentPublishedVersion->definition_hash, $first->definition_hash);
        $this->assertDatabaseCount('automation_workflow_executions', 1);
    }

    public function test_stop_step_completes_execution_once(): void
    {
        $execution = $this->start($this->published($this->stops()), 'stop');
        app(StartAutomationWorkflowExecution::class, ['executionId' => $execution->id])->handle(app(TenantContext::class));
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame(AutomationWorkflowExecutionStatus::Completed, $execution->refresh()->status);
        $this->assertDatabaseCount('automation_workflow_step_executions', 1);
    }

    public function test_branch_selects_expected_path(): void
    {
        $condition = ['type' => 'group', 'version' => 1, 'logic' => 'and', 'children' => [['type' => 'rule', 'field' => 'trigger.type', 'operator' => 'equals', 'value' => 'manual']]];
        $steps = [new AutomationWorkflowStepData('branch', null, 'branch', 1, null, ['condition' => $condition, 'true_step_key' => 'yes', 'false_step_key' => 'no']), new AutomationWorkflowStepData('yes', 'branch', 'stop', 2, null, []), new AutomationWorkflowStepData('no', 'branch', 'stop', 3, null, [])];
        $execution = $this->running($this->start($this->published($steps), 'branch'));
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame('yes', $execution->refresh()->current_step_key);
        $this->assertSame('yes', $execution->stepExecutions()->first()->next_step_key);
    }

    public function test_delay_waits_and_resumes_once_when_due(): void
    {
        $steps = [new AutomationWorkflowStepData('wait', null, 'delay', 1, null, ['delay_type' => 'duration', 'seconds' => 60, 'next_step_key' => 'done']), new AutomationWorkflowStepData('done', 'wait', 'stop', 2, null, [])];
        $execution = $this->running($this->start($this->published($steps), 'delay'));
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame(AutomationWorkflowExecutionStatus::Waiting, $execution->refresh()->status);
        $step = $execution->stepExecutions()->first();
        $this->travel(61)->seconds();
        (new ResumeAutomationWorkflowDelay($execution->id, $step->id))->handle();
        (new ResumeAutomationWorkflowDelay($execution->id, $step->id))->handle();
        $this->assertSame('done', $execution->refresh()->current_step_key);
        $this->assertSame(1, $execution->processed_steps);
    }

    public function test_cancellation_prevents_future_processing(): void
    {
        $execution = $this->start($this->published($this->stops()), 'cancel');
        app(CancelAutomationWorkflowExecutionService::class)->cancel($execution, $this->actor);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame(AutomationWorkflowExecutionStatus::Cancelled, $execution->refresh()->status);
        $this->assertDatabaseCount('automation_workflow_step_executions', 0);
    }

    public function test_step_limit_terminates_corrupt_execution_safely(): void
    {
        $execution = $this->running($this->start($this->published($this->stops()), 'limit'));
        $execution->forceFill(['processed_steps' => $execution->maximum_steps])->save();
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame(AutomationWorkflowExecutionStatus::Failed, $execution->refresh()->status);
        $this->assertSame('step_limit_exceeded', $execution->failure_code);
    }

    private function published(array $steps, bool $enable = true)
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create(new CreateAutomationWorkflowData('Runtime', null, 'manual', [], ['maximum_execution_minutes' => 60, 'maximum_steps' => 10, 'allow_reentry' => false, 'maximum_active_executions_per_contact' => 1], $steps), $this->actor);
        app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);

        return $enable ? app(EnableAutomationWorkflowService::class)->enable($workflow->refresh(), new EnableAutomationWorkflowData(2), $this->actor) : $workflow->refresh();
    }

    private function start($workflow, string $key): AutomationWorkflowExecution
    {
        return app(StartAutomationWorkflowService::class)->start($workflow, new StartAutomationWorkflowData($key, new AutomationWorkflowExecutionContext(['type' => 'manual', 'reference' => null], ['uuid' => null], [])), $this->actor);
    }

    private function running(AutomationWorkflowExecution $execution): AutomationWorkflowExecution
    {
        (new StartAutomationWorkflowExecution($execution->id))->handle(app(TenantContext::class));

        return $execution->refresh();
    }

    private function stops(): array
    {
        return [new AutomationWorkflowStepData('stop', null, 'stop', 1, null, [])];
    }
}
