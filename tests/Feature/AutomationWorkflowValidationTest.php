<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\AutomationWorkflowStepData;
use App\Data\Automations\CreateAutomationWorkflowData;
use App\Models\ContactGroup;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Automations\CreateAutomationWorkflowService;
use App\Services\Automations\ValidateAutomationWorkflowVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutomationWorkflowValidationTest extends TestCase
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
        $entitlements->shouldReceive('requireFeature')->zeroOrMoreTimes();
        $entitlements->shouldReceive('requireCapacity')->zeroOrMoreTimes();
    }

    public function test_reserved_trigger_is_rejected_for_publication(): void
    {
        $result = $this->validate('incoming_whatsapp_message', [], $this->stopSteps(), true);

        $this->assertFalse($result->valid);
        $this->assertContains('unsupported_in_current_version', array_column($result->errors, 'code'));
    }

    public function test_trigger_reference_must_belong_to_active_tenant(): void
    {
        $other = Tenant::factory()->create();
        $group = ContactGroup::create(['tenant_id' => $other->id, 'name' => 'Other', 'created_by' => $this->actor->id]);
        $result = $this->validate('contact_added_to_group', ['group_uuid' => $group->uuid], $this->stopSteps(), true);

        $this->assertContains('tenant_reference_invalid', array_column($result->errors, 'code'));
    }

    public function test_condition_fields_are_allowlisted_and_trigger_compatible(): void
    {
        $condition = ['type' => 'group', 'version' => 1, 'logic' => 'and', 'children' => [['type' => 'rule', 'field' => 'trigger.changed_fields', 'operator' => 'contains', 'value' => 'company']]];
        $steps = [
            new AutomationWorkflowStepData('branch', null, 'branch', 1, null, ['condition' => $condition, 'true_step_key' => 'yes', 'false_step_key' => 'no']),
            new AutomationWorkflowStepData('yes', 'branch', 'stop', 2, null, []),
            new AutomationWorkflowStepData('no', 'branch', 'stop', 3, null, []),
        ];
        $result = $this->validate('contact_created', [], $steps, true);

        $this->assertFalse($result->valid);
        $this->assertContains('invalid_condition', array_column($result->errors, 'code'));
    }

    public function test_delay_must_be_a_bounded_fixed_duration(): void
    {
        $steps = [
            new AutomationWorkflowStepData('wait', null, 'delay', 1, null, ['delay_type' => 'duration', 'seconds' => 59, 'next_step_key' => 'done']),
            new AutomationWorkflowStepData('done', 'wait', 'stop', 2, null, []),
        ];
        $result = $this->validate('manual', [], $steps, true);

        $this->assertContains('invalid_delay', array_column($result->errors, 'code'));
    }

    public function test_graph_rejects_cycles_and_missing_entry(): void
    {
        $steps = [
            new AutomationWorkflowStepData('a', 'b', 'delay', 1, null, ['delay_type' => 'duration', 'seconds' => 60, 'next_step_key' => 'b']),
            new AutomationWorkflowStepData('b', 'a', 'delay', 2, null, ['delay_type' => 'duration', 'seconds' => 60, 'next_step_key' => 'a']),
        ];
        $result = $this->validate('manual', [], $steps, true);
        $codes = array_column($result->errors, 'code');

        $this->assertContains('entry_step_missing', $codes);
        $this->assertContains('cycle_detected', $codes);
    }

    public function test_hash_is_deterministic_and_validation_is_persisted(): void
    {
        $first = $this->validate('manual', [], $this->stopSteps(), false);
        $second = $this->validate('manual', [], $this->stopSteps(), false);

        $this->assertTrue($first->valid);
        $this->assertSame($first->definitionHash, $second->definitionHash);
        $this->assertDatabaseHas('automation_workflow_versions', ['definition_hash' => $first->definitionHash, 'validation_status' => 'valid']);
    }

    private function validate(string $trigger, array $configuration, array $steps, bool $publishing): object
    {
        $workflow = app(CreateAutomationWorkflowService::class)->create(new CreateAutomationWorkflowData(
            'Validation workflow', null, $trigger, $configuration,
            ['maximum_execution_minutes' => 60, 'maximum_steps' => 10, 'allow_reentry' => false, 'maximum_active_executions_per_contact' => 1],
            $steps,
        ), $this->actor);

        return app(ValidateAutomationWorkflowVersionService::class)->validate($workflow->currentDraftVersion, $publishing);
    }

    private function stopSteps(): array
    {
        return [new AutomationWorkflowStepData('stop', null, 'stop', 1, 'Done', [])];
    }
}
