<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationActionExecutionContext;
use App\Enums\AutomationWorkflowExecutionStatus as ExecutionStatus;
use App\Enums\AutomationWorkflowFailureClass;
use App\Enums\AutomationWorkflowStepExecutionStatus as StepStatus;
use App\Jobs\ContinueAutomationWorkflowExecution;
use App\Jobs\ResumeAutomationWorkflowDelay;
use App\Models\AutomationWorkflowExecution;
use App\Models\AutomationWorkflowStepExecution;
use Illuminate\Support\Facades\DB;

final class ProcessAutomationWorkflowStepService
{
    public function __construct(private AutomationConditionEvaluator $conditions, private AutomationWorkflowStepHandlerRegistry $handlers, private AutomationActionHandlerRegistry $actions, private AutomationWorkflowRetryPolicy $retries) {}

    public function process(int $executionId): void
    {
        $next = false;
        DB::transaction(function () use ($executionId, &$next) {
            $e = AutomationWorkflowExecution::with(['version.steps'])->lockForUpdate()->findOrFail($executionId);
            if ($e->status !== ExecutionStatus::Running) {
                return;
            }
            if ($e->cancel_requested_at) {
                $this->cancel($e);

                return;
            } if ($e->started_at?->addMinutes($e->maximum_execution_minutes)->isPast()) {
                $this->fail($e, 'execution_timed_out', true);

                return;
            } if ($e->processed_steps >= $e->maximum_steps) {
                $this->fail($e, 'step_limit_exceeded');

                return;
            }
            $step = $e->version->steps->firstWhere('step_key', $e->current_step_key);
            if (! $step) {
                $this->fail($e, 'workflow_version_stale');

                return;
            }
            $attempt = (int) $e->stepExecutions()->where('step_key', $step->step_key)->max('attempt_number') + 1;
            if ($attempt > config('automations.execution.step_max_attempts')) {
                $this->fail($e, 'attempt_limit_reached');

                return;
            }
            $idem = hash('sha256', "{$e->uuid}|{$step->step_key}|{$attempt}|{$e->definition_hash}");
            $se = AutomationWorkflowStepExecution::firstOrCreate(['idempotency_key' => $idem], ['tenant_id' => $e->tenant_id, 'automation_workflow_execution_id' => $e->id, 'automation_workflow_step_id' => $step->id, 'step_key' => $step->step_key, 'step_type' => $step->step_type->value, 'status' => StepStatus::Processing, 'attempt_number' => $attempt, 'started_at' => now(), 'input_snapshot' => ['step_key' => $step->step_key]]);
            if ($se->status !== StepStatus::Processing) {
                return;
            }
            $config = $step->configuration ?? [];
            $nextKey = null;
            $output = [];
            try {
                if ($step->step_type->value !== 'action' && ! $this->handlers->available($step->step_type)) {
                    $this->stepFail($se, $e, 'action_handler_unavailable');

                    return;
                }
                if ($step->step_type->value === 'action') {
                    $result = $this->actions->resolve($config['action_type'])->execute(new AutomationActionExecutionContext($e->uuid, $se->uuid, $step->step_key, $attempt, $e->context ?? [], $config['parameters']));
                    if (! $result->successful) {
                        if ($result->retryable) {
                            $decision = $this->retries->decide(AutomationWorkflowFailureClass::Transient, $attempt);
                            if ($decision->retry) {
                                $se->forceFill(['status' => StepStatus::RetryScheduled, 'failure_class' => AutomationWorkflowFailureClass::Transient, 'failure_code' => $result->failureCode, 'failure_message' => $result->safeMessage])->save();
                                ContinueAutomationWorkflowExecution::dispatch($e->id)->delay($decision->retryAt);

                                return;
                            }
                        }
                        $this->stepFail($se, $e, $result->failureCode ?? 'internal_error');

                        return;
                    }
                    $output = $result->output;
                    $nextKey = $result->stopWorkflow ? null : ($config['next_step_key'] ?? null);
                    if ($result->stopWorkflow) {
                        $se->forceFill(['status' => StepStatus::Completed, 'completed_at' => now(), 'output_snapshot' => $output])->save();
                        $e->forceFill(['status' => ExecutionStatus::Completed, 'current_step_key' => null, 'completed_at' => now(), 'processed_steps' => $e->processed_steps + 1, 'last_heartbeat_at' => now()])->save();

                        return;
                    }
                } elseif (in_array($step->step_type->value, ['condition', 'branch'], true)) {
                    $value = $this->conditions->evaluate($config['condition'], $e);
                    $nextKey = $step->step_type->value === 'branch' ? ($value ? $config['true_step_key'] : $config['false_step_key']) : ($config['next_step_key'] ?? null);
                    $output = ['result' => $value, 'selected_step_key' => $nextKey];
                } elseif ($step->step_type->value === 'delay') {
                    $until = now()->addSeconds((int) $config['seconds']);
                    $se->forceFill(['status' => StepStatus::Waiting, 'waiting_until' => $until, 'output_snapshot' => ['waiting_until' => $until->toISOString()], 'next_step_key' => $config['next_step_key'] ?? null])->save();
                    $e->forceFill(['status' => ExecutionStatus::Waiting, 'waiting_until' => $until, 'last_heartbeat_at' => now()])->save();
                    ResumeAutomationWorkflowDelay::dispatch($e->id, $se->id)->delay($until);

                    return;
                } elseif ($step->step_type->value === 'stop') {
                    $se->forceFill(['status' => StepStatus::Completed, 'completed_at' => now(), 'output_snapshot' => ['stopped' => true]])->save();
                    $e->forceFill(['status' => ExecutionStatus::Completed, 'current_step_key' => null, 'completed_at' => now(), 'processed_steps' => $e->processed_steps + 1, 'last_heartbeat_at' => now()])->save();

                    return;
                } else {
                    $nextKey = $config['next_step_key'] ?? null;
                }
                $se->forceFill(['status' => StepStatus::Completed, 'completed_at' => now(), 'output_snapshot' => $output, 'next_step_key' => $nextKey])->save();
                $e->forceFill(['current_step_key' => $nextKey, 'processed_steps' => $e->processed_steps + 1, 'last_heartbeat_at' => now()])->save();
                if (! $nextKey) {
                    $e->forceFill(['status' => ExecutionStatus::Completed, 'completed_at' => now()])->save();
                } else {
                    $next = true;
                }
            } catch (\Throwable $x) {
                $code = in_array($x->getMessage(), ['contact_missing', 'execution_cancelled', 'subscription_inactive', 'tenant_mismatch'], true) ? $x->getMessage() : 'condition_evaluation_failed';
                $this->stepFail($se, $e, $code);
            }
        }, 3);
        if ($next) {
            ContinueAutomationWorkflowExecution::dispatch($executionId);
        }
    }

    private function stepFail(AutomationWorkflowStepExecution $s, AutomationWorkflowExecution $e, string $code): void
    {
        $s->forceFill(['status' => StepStatus::Failed, 'failed_at' => now(), 'failure_class' => AutomationWorkflowFailureClass::Permanent, 'failure_code' => $code, 'failure_message' => 'Workflow step could not be processed.'])->save();
        $this->fail($e, $code);
    }

    private function fail(AutomationWorkflowExecution $e, string $code, bool $timeout = false): void
    {
        $e->forceFill(['status' => $timeout ? ExecutionStatus::TimedOut : ExecutionStatus::Failed, 'failure_code' => $code, 'failure_message' => 'Workflow execution stopped safely.', $timeout ? 'timed_out_at' : 'failed_at' => now(), 'last_heartbeat_at' => now()])->save();
    }

    private function cancel(AutomationWorkflowExecution $e): void
    {
        $e->stepExecutions()->whereIn('status', ['pending', 'waiting', 'retry_scheduled'])->update(['status' => StepStatus::Cancelled->value, 'cancelled_at' => now()]);
        $e->forceFill(['status' => ExecutionStatus::Cancelled, 'cancelled_at' => now(), 'waiting_until' => null])->save();
    }
}
