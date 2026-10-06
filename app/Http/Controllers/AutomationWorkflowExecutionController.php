<?php

namespace App\Http\Controllers;

use App\Data\Automations\AutomationWorkflowExecutionContext;
use App\Data\Automations\StartAutomationWorkflowData;
use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowExecution;
use App\Services\Automations\AutomationWorkflowExecutionQuery;
use App\Services\Automations\CancelAutomationWorkflowExecutionService;
use App\Services\Automations\StartAutomationWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AutomationWorkflowExecutionController extends Controller
{
    public function start(Request $request, AutomationWorkflow $workflow, StartAutomationWorkflowService $service): JsonResponse|RedirectResponse
    {
        $this->authorize('execute', $workflow);
        $v = $request->validate(['idempotency_key' => 'required|string|max:191', 'context' => 'array', 'context.contact.uuid' => 'nullable|uuid', 'context.values' => 'array']);
        $ctx = $v['context'] ?? [];
        $execution = $service->start($workflow, new StartAutomationWorkflowData($v['idempotency_key'], new AutomationWorkflowExecutionContext(['type' => 'manual', 'reference' => null], ['uuid' => data_get($ctx, 'contact.uuid')], $ctx['values'] ?? [])), $request->user());

        return $request->expectsJson() ? response()->json(['data' => $execution], 202) : redirect()->route('tenant.automation-executions.show', $execution)->with('status', 'Workflow execution queued.');
    }

    public function index(Request $request, AutomationWorkflow $workflow, AutomationWorkflowExecutionQuery $query): JsonResponse|View
    {
        $this->authorize('view', $workflow);

        $executions = $query->paginate(['workflow_id' => $workflow->id] + $request->only(['status', 'trigger_type', 'failure_code', 'per_page']));

        return $request->expectsJson() ? response()->json($executions) : view('tenant.automations.executions.index', compact('workflow', 'executions'));
    }

    public function show(AutomationWorkflowExecution $execution): JsonResponse|View
    {
        $this->authorize('view', $execution);

        $execution->load(['workflow:id,uuid,name', 'version:id,uuid,version_number', 'stepExecutions' => fn ($query) => $query->orderBy('created_at')]);

        return request()->expectsJson() ? response()->json(['data' => $execution]) : view('tenant.automations.executions.show', compact('execution'));
    }

    public function cancel(Request $request, AutomationWorkflowExecution $execution, CancelAutomationWorkflowExecutionService $service): JsonResponse|RedirectResponse
    {
        $this->authorize('cancel', $execution);

        $cancelled = $service->cancel($execution, $request->user());

        return $request->expectsJson() ? response()->json(['data' => $cancelled]) : back()->with('status', 'Execution cancelled. Completed actions were not rolled back.');
    }
}
