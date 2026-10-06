<?php

namespace App\Services\Automations;

use App\Contracts\AutomationActionHandler;
use App\Data\Automations\AutomationActionExecutionContext;
use App\Data\Automations\AutomationActionResult;
use App\Enums\AutomationActionType;
use App\Enums\ContactConsentStatus;
use App\Enums\ContactStatus;
use App\Enums\WhatsAppSessionStatus;
use App\Models\AutomationWorkflowStepExecution;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Models\WhatsAppSession;
use App\Services\AuditService;
use App\Services\Templates\CreateTransactionalWhatsAppMessageFromTemplateService;
use Illuminate\Validation\ValidationException;

final class SendWhatsAppTemplateAutomationActionHandler implements AutomationActionHandler
{
    public function __construct(private AutomationActionRuntime $runtime, private AutomationActionValueResolver $values, private CreateTransactionalWhatsAppMessageFromTemplateService $messages, private AuditService $audit) {}

    public function supports(AutomationActionType $type): bool
    {
        return $type === AutomationActionType::SendWhatsAppTemplate;
    }

    public function execute(AutomationActionExecutionContext $context): AutomationActionResult
    {
        try {
            $completed = AutomationWorkflowStepExecution::where('uuid', $context->stepExecutionUuid)->where('status', 'completed')->first();
            if ($completed && data_get($completed->output_snapshot, 'message_uuid')) {
                return new AutomationActionResult(true, output: $completed->output_snapshot);
            }
            [$e,$actor,$contact] = $this->runtime->resolve($context);
            if ($contact->status !== ContactStatus::Active) {
                return $this->fail('contact_inactive');
            }if ($contact->opted_out_at) {
                return $this->fail('contact_opted_out');
            }if ($contact->suppressed_at) {
                return $this->fail('contact_suppressed');
            }if ($contact->blocked_at) {
                return $this->fail('contact_blocked');
            }if ($contact->consent_status !== ContactConsentStatus::Granted || $contact->consent_expires_at?->isPast()) {
                return $this->fail('consent_not_granted');
            }$p = $context->parameters;
            $version = WhatsAppMessageTemplateVersion::where('uuid', $p['template_version_uuid'])->where('content_hash', $p['template_content_hash'])->first();
            if (! $version) {
                return $this->fail('template_missing');
            }$resolved = [];
            foreach ($p['variable_mappings'] ?? [] as $key => $mapping) {
                $resolved[$key] = $this->values->resolve($mapping, $contact, $context->context);
            }$session = ($p['session_strategy'] ?? 'automatic') === 'specific'
                ? WhatsAppSession::forTenant($e->tenant_id)->where('uuid', $p['session_uuid'])->where('status', WhatsAppSessionStatus::Ready)->first()
                : WhatsAppSession::forTenant($e->tenant_id)
                    ->where('status', WhatsAppSessionStatus::Ready)
                    ->orderByRaw('CASE WHEN next_send_at IS NULL THEN 0 ELSE 1 END')
                    ->orderBy('next_send_at')
                    ->orderBy('id')
                    ->first();
            if (! $session) {
                return $this->fail('temporary_internal_failure', true);
            }$key = hash('sha256', implode('|', [$e->uuid, $context->stepExecutionUuid, $context->stepKey, $context->attemptNumber, $version->uuid, $version->content_hash, $contact->uuid]));
            $message = $this->messages->create($e->tenant, $actor, [
                'session_uuid' => $session->uuid,
                'recipient' => $contact->phone_normalized,
                'template_uuid' => $p['template_uuid'],
                'template_version_uuid' => $version->uuid,
                'values' => $resolved,
                'timezone' => $contact->timezone ?: 'UTC',
                'idempotency_key' => $key,
                'metadata' => [
                    'source' => 'automation',
                    'workflow_uuid' => $e->workflow->uuid,
                    'workflow_execution_uuid' => $e->uuid,
                    'workflow_step_key' => $context->stepKey,
                ],
            ]);
            $output = ['message_uuid' => $message->uuid, 'message_status' => $message->status->value, 'template_uuid' => $p['template_uuid'], 'template_version_uuid' => $version->uuid];
            $this->audit->recordDomain('automation_workflow.message_queued', $actor, $e->tenant, $e, ['workflow_uuid' => $e->workflow->uuid, 'execution_uuid' => $e->uuid, 'step_key' => $context->stepKey, 'action_type' => 'send_whatsapp_template', 'contact_uuid' => $contact->uuid, 'message_uuid' => $message->uuid]);

            return new AutomationActionResult(true, output: $output);
        } catch (ValidationException $x) {
            $code = (string) collect($x->errors())->flatten()->first();

            return $this->fail(in_array($code, ['execution_cancelled', 'contact_missing'], true) ? $code : 'template_render_failed');
        } catch (\Throwable) {
            return $this->fail('temporary_internal_failure', true);
        }
    }

    private function fail(string $code, bool $retry = false): AutomationActionResult
    {
        return new AutomationActionResult(false, $retry, $code, 'Workflow action could not be completed.');
    }
}
