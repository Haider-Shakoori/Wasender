<?php

namespace Tests\Feature;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationWorkflowExecutionContext;
use App\Data\Automations\AutomationWorkflowStepData;
use App\Data\Automations\CreateAutomationWorkflowData;
use App\Data\Automations\EnableAutomationWorkflowData;
use App\Data\Automations\PublishAutomationWorkflowData;
use App\Data\Automations\StartAutomationWorkflowData;
use App\Enums\ContactConsentStatus;
use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Enums\WhatsAppSessionStatus;
use App\Jobs\StartAutomationWorkflowExecution;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Models\WhatsAppSession;
use App\Services\Automations\CreateAutomationWorkflowService;
use App\Services\Automations\EnableAutomationWorkflowService;
use App\Services\Automations\ProcessAutomationWorkflowStepService;
use App\Services\Automations\PublishAutomationWorkflowService;
use App\Services\Automations\SendWhatsAppTemplateAutomationActionHandler;
use App\Services\Automations\StartAutomationWorkflowService;
use App\Services\Templates\WhatsAppMessageTemplateContentHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AutomationWorkflowActionTest extends TestCase
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
        $e = $this->mock(TenantEntitlements::class);
        $e->shouldReceive('requireFeature')->zeroOrMoreTimes();
        $e->shouldReceive('requireCapacity')->zeroOrMoreTimes();
    }

    public function test_unsupported_action_blocks_publication(): void
    {
        $workflow = $this->draft([new AutomationWorkflowStepData('action', null, 'action', 1, null, ['action_type' => 'send_webhook', 'parameters' => []])]);
        $this->expectException(ValidationException::class);
        app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);
    }

    public function test_cross_tenant_label_is_rejected(): void
    {
        $other = Tenant::factory()->create();
        $label = ContactLabel::create(['tenant_id' => $other->id, 'name' => 'Other', 'slug' => 'other', 'is_active' => true, 'created_by' => $this->actor->id]);
        $workflow = $this->draft([new AutomationWorkflowStepData('action', null, 'action', 1, null, ['action_type' => 'add_contact_label', 'parameters' => ['label_uuid' => $label->uuid]])]);
        $this->expectException(ValidationException::class);
        app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);
    }

    public function test_label_and_group_actions_are_idempotent(): void
    {
        $contact = $this->contact();
        $label = ContactLabel::create(['tenant_id' => $this->tenant->id, 'name' => 'VIP', 'slug' => 'vip', 'is_active' => true, 'created_by' => $this->actor->id]);
        $group = ContactGroup::create(['tenant_id' => $this->tenant->id, 'name' => 'Buyers', 'is_active' => true, 'created_by' => $this->actor->id]);
        $steps = [new AutomationWorkflowStepData('label', null, 'action', 1, null, ['action_type' => 'add_contact_label', 'parameters' => ['label_uuid' => $label->uuid], 'next_step_key' => 'group']), new AutomationWorkflowStepData('group', 'label', 'action', 2, null, ['action_type' => 'add_contact_to_group', 'parameters' => ['group_uuid' => $group->uuid], 'next_step_key' => 'stop']), new AutomationWorkflowStepData('stop', 'group', 'stop', 3, null, [])];
        $workflow = $this->draft($steps);
        app(PublishAutomationWorkflowService::class)->publish($workflow, new PublishAutomationWorkflowData(1), $this->actor);
        $workflow = app(EnableAutomationWorkflowService::class)->enable($workflow->refresh(), new EnableAutomationWorkflowData(2), $this->actor);
        $execution = $this->running($workflow, $contact);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame(1, $label->contacts()->whereKey($contact->id)->count());
        $this->assertSame(1, $group->contacts()->whereKey($contact->id)->count());
    }

    public function test_update_contact_changes_only_approved_field(): void
    {
        $contact = $this->contact();
        $steps = [new AutomationWorkflowStepData('update', null, 'action', 1, null, ['action_type' => 'update_contact', 'parameters' => ['updates' => ['company' => ['source' => 'constant', 'value' => 'Acme']]], 'next_step_key' => 'stop']), new AutomationWorkflowStepData('stop', 'update', 'stop', 2, null, [])];
        $execution = $this->running($this->operational($steps), $contact);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame('Acme', $contact->refresh()->company);
        $this->assertSame('+15551234567', $contact->phone_normalized);
    }

    public function test_template_action_queues_one_idempotent_transactional_message(): void
    {
        $contact = $this->contact();
        $template = $this->template();
        WhatsAppSession::create(['tenant_id' => $this->tenant->id, 'name' => 'Ready', 'storage_key' => 'wa_'.str_repeat('a', 48), 'status' => WhatsAppSessionStatus::Ready, 'created_by' => $this->actor->id]);
        $parameters = ['template_uuid' => $template->uuid, 'session_strategy' => 'automatic', 'session_uuid' => null, 'variable_mappings' => ['first_name' => ['source' => 'contact', 'key' => 'first_name']]];
        $steps = [new AutomationWorkflowStepData('send', null, 'action', 1, null, ['action_type' => 'send_whatsapp_template', 'parameters' => $parameters, 'next_step_key' => 'stop']), new AutomationWorkflowStepData('stop', 'send', 'stop', 2, null, [])];
        $execution = $this->running($this->operational($steps), $contact);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $step = $execution->stepExecutions()->first();
        $config = $execution->version->steps()->where('step_key', 'send')->first()->configuration;
        $this->assertSame($template->currentPublishedVersion->uuid, $config['parameters']['template_version_uuid']);
        $result = app(SendWhatsAppTemplateAutomationActionHandler::class)->execute(new AutomationActionExecutionContext($execution->uuid, $step->uuid, 'send', 1, $execution->context, $config['parameters']));
        $this->assertTrue($result->successful, $result->failureCode ?? 'unknown');
        $this->assertSame(1, WhatsAppMessage::where('tenant_id', $this->tenant->id)->count());
        $this->assertSame('queued', WhatsAppMessage::first()->status->value);
    }

    public function test_opted_out_contact_cannot_receive_template_action(): void
    {
        $contact = $this->contact();
        $contact->forceFill(['opted_out_at' => now()])->save();
        $template = $this->template();
        WhatsAppSession::create(['tenant_id' => $this->tenant->id, 'name' => 'Ready', 'storage_key' => 'wa_'.str_repeat('b', 48), 'status' => WhatsAppSessionStatus::Ready, 'created_by' => $this->actor->id]);
        $parameters = ['template_uuid' => $template->uuid, 'session_strategy' => 'automatic', 'session_uuid' => null, 'variable_mappings' => ['first_name' => ['source' => 'contact', 'key' => 'first_name']]];
        $steps = [new AutomationWorkflowStepData('send', null, 'action', 1, null, ['action_type' => 'send_whatsapp_template', 'parameters' => $parameters])];
        $execution = $this->running($this->operational($steps), $contact);
        app(ProcessAutomationWorkflowStepService::class)->process($execution->id);
        $this->assertSame('contact_opted_out', $execution->refresh()->failure_code);
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    private function draft(array $steps)
    {
        return app(CreateAutomationWorkflowService::class)->create(new CreateAutomationWorkflowData('Actions', null, 'manual', [], ['maximum_execution_minutes' => 60, 'maximum_steps' => 10, 'allow_reentry' => false, 'maximum_active_executions_per_contact' => 1], $steps), $this->actor);
    }

    private function operational(array $steps)
    {
        $w = $this->draft($steps);
        app(PublishAutomationWorkflowService::class)->publish($w, new PublishAutomationWorkflowData(1), $this->actor);

        return app(EnableAutomationWorkflowService::class)->enable($w->refresh(), new EnableAutomationWorkflowData(2), $this->actor);
    }

    private function running($workflow, Contact $contact)
    {
        $e = app(StartAutomationWorkflowService::class)->start($workflow, new StartAutomationWorkflowData('run-'.uniqid(), new AutomationWorkflowExecutionContext(['type' => 'manual', 'reference' => null], ['uuid' => $contact->uuid], [])), $this->actor);
        (new StartAutomationWorkflowExecution($e->id))->handle(app(TenantContext::class));

        return $e->refresh();
    }

    private function contact(): Contact
    {
        return Contact::create(['tenant_id' => $this->tenant->id, 'created_by' => $this->actor->id, 'first_name' => 'Alex', 'phone_input' => '+15551234567', 'phone_normalized' => '+15551234567', 'whatsapp_address' => '15551234567@c.us', 'status' => ContactStatus::Active, 'consent_status' => ContactConsentStatus::Granted, 'source' => ContactSource::Manual, 'timezone' => 'UTC']);
    }

    private function template(): WhatsAppMessageTemplate
    {
        $t = WhatsAppMessageTemplate::forceCreate(['tenant_id' => $this->tenant->id, 'name' => 'Hello', 'type' => 'text', 'status' => WhatsAppMessageTemplateStatus::Draft, 'created_by' => $this->actor->id]);
        $v = WhatsAppMessageTemplateVersion::forceCreate(['whatsapp_message_template_id' => $t->id, 'version_number' => 1, 'status' => WhatsAppMessageTemplateVersionStatus::Draft, 'body' => 'Hello {{first_name}}', 'variable_context' => 'contact', 'variable_configuration' => ['first_name' => ['required' => true, 'default' => null]], 'parser_version' => 1, 'renderer_version' => 1, 'created_by' => $this->actor->id]);
        $v->setRelation('template', $t);
        $v->setRelation('attachment', null);
        $hash = app(WhatsAppMessageTemplateContentHasher::class)->hash($v, $t->type);
        $v->forceFill(['content_hash' => $hash, 'status' => WhatsAppMessageTemplateVersionStatus::Published, 'published_at' => now()])->save();
        $t->forceFill(['status' => WhatsAppMessageTemplateStatus::Published, 'current_published_version_id' => $v->id, 'published_at' => now()])->save();

        return $t->refresh();
    }
}
