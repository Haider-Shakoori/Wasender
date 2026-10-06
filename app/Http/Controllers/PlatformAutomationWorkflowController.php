<?php

namespace App\Http\Controllers;

use App\Models\AutomationWorkflow;
use App\Models\AutomationWorkflowExecution;
use App\Services\Automations\PlatformAutomationWorkflowQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformAutomationWorkflowController extends Controller
{
    public function index(Request $r, PlatformAutomationWorkflowQuery $q): View
    {
        return view('platform.automations.index', ['workflows' => $q->paginate($r->only(['tenant_id', 'status', 'enabled', 'trigger_type', 'created_from', 'updated_from']))]);
    }

    public function show(AutomationWorkflow $workflow): View
    {
        $workflow->load(['tenant:id,uuid,name', 'creator:id,name', 'updater:id,name', 'currentDraftVersion', 'currentPublishedVersion', 'versions' => fn ($query) => $query->withCount('steps')->latest('version_number')]);
        $executions = $workflow->executions()->latest()->paginate(20);

        return view('platform.automations.show', compact('workflow', 'executions'));
    }

    public function executions(Request $request): View
    {
        $query = AutomationWorkflowExecution::with(['tenant:id,uuid,name', 'workflow:id,uuid,name', 'version:id,uuid,version_number']);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->integer('tenant_id'));
        }

        return view('platform.automation-executions.index', ['executions' => $query->latest()->paginate(25)->withQueryString()]);
    }

    public function execution(AutomationWorkflowExecution $execution): View
    {
        $execution->load(['tenant:id,uuid,name', 'workflow:id,uuid,name', 'version:id,uuid,version_number', 'stepExecutions' => fn ($query) => $query->orderBy('created_at')]);

        return view('platform.automation-executions.show', compact('execution'));
    }
}
