<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Automations\CreateAutomationWorkflowData;
use App\Data\Automations\UpdateAutomationWorkflowDraftData;
use App\Http\Requests\StoreAutomationWorkflowRequest;
use App\Http\Requests\UpdateAutomationWorkflowRequest;
use App\Models\AutomationWorkflow;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\WhatsAppMessageTemplate;
use App\Services\Automations\AutomationWorkflowQuery;
use App\Services\Automations\CreateAutomationWorkflowService;
use App\Services\Automations\UpdateAutomationWorkflowDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AutomationWorkflowController extends Controller
{
    public function index(Request $request, AutomationWorkflowQuery $query): View|JsonResponse
    {
        $this->authorize('viewAny', AutomationWorkflow::class);
        $workflows = $query->paginate($request->only(['search', 'status', 'enabled', 'sort', 'direction']));

        return $request->expectsJson() ? response()->json($workflows) : view('tenant.automations.index', compact('workflows'));
    }

    public function create(TenantContext $tenant): View
    {
        $this->authorize('create', AutomationWorkflow::class);

        return view('tenant.automations.form', $this->resources($tenant));
    }

    public function store(StoreAutomationWorkflowRequest $request, CreateAutomationWorkflowService $service): RedirectResponse|JsonResponse
    {
        $this->authorize('create', AutomationWorkflow::class);
        $workflow = $service->create(CreateAutomationWorkflowData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($workflow, 201) : redirect()->route('tenant.automations.show', $workflow)->with('status', 'Workflow draft created.');
    }

    public function show(AutomationWorkflow $workflow, TenantContext $tenant): View|JsonResponse
    {
        abort_unless($workflow->tenant_id === $tenant->id(), 404);
        $this->authorize('view', $workflow);
        $workflow->load(['currentDraftVersion.steps', 'currentPublishedVersion.steps', 'versions' => fn ($query) => $query->withCount('steps')->latest('version_number')]);
        $executions = $workflow->executions()->latest()->paginate(15, ['*'], 'executions_page');
        $contacts = $this->requiresContact($workflow) ? Contact::where('tenant_id', $tenant->id())->whereNull('deleted_at')->where('status', 'active')->orderBy('first_name')->limit(200)->get(['id', 'uuid', 'first_name', 'last_name', 'phone_normalized']) : collect();

        return request()->expectsJson() ? response()->json($workflow) : view('tenant.automations.show', compact('workflow', 'executions', 'contacts'));
    }

    public function edit(AutomationWorkflow $workflow, TenantContext $tenant): View
    {
        abort_unless($workflow->tenant_id === $tenant->id(), 404);
        $this->authorize('update', $workflow);
        abort_unless($workflow->currentDraftVersion, 422, 'Create a draft version before editing.');
        $workflow->load('currentDraftVersion.steps');

        return view('tenant.automations.form', ['workflow' => $workflow] + $this->resources($tenant));
    }

    public function update(UpdateAutomationWorkflowRequest $request, AutomationWorkflow $workflow, TenantContext $tenant, UpdateAutomationWorkflowDraftService $service): RedirectResponse|JsonResponse
    {
        abort_unless($workflow->tenant_id === $tenant->id(), 404);
        $this->authorize('update', $workflow);
        $updated = $service->update($workflow, UpdateAutomationWorkflowDraftData::from($request->validated()), $request->user());

        return $request->expectsJson() ? response()->json($updated) : redirect()->route('tenant.automations.show', $updated)->with('status', 'Workflow draft updated.');
    }

    private function resources(TenantContext $tenant): array
    {
        return [
            'templates' => WhatsAppMessageTemplate::forTenant($tenant->id())->where('status', 'published')->with('currentPublishedVersion')->orderBy('name')->get(),
            'labels' => ContactLabel::where('tenant_id', $tenant->id())->orderBy('name')->get(),
            'groups' => ContactGroup::where('tenant_id', $tenant->id())->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function requiresContact(AutomationWorkflow $workflow): bool
    {
        $version = $workflow->currentPublishedVersion ?? $workflow->currentDraftVersion;

        return (bool) $version?->steps->contains(fn ($step) => $step->step_type->value === 'action' && ($step->configuration['action_type'] ?? null) !== 'stop_workflow');
    }
}
