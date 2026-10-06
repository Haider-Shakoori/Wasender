<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Data\Automations\ArchiveAutomationWorkflowData;
use App\Data\Automations\CreateAutomationWorkflowDraftVersionData;
use App\Data\Automations\DisableAutomationWorkflowData;
use App\Data\Automations\DuplicateAutomationWorkflowData;
use App\Data\Automations\EnableAutomationWorkflowData;
use App\Data\Automations\PublishAutomationWorkflowData;
use App\Data\Automations\RestoreAutomationWorkflowData;
use App\Http\Requests\ArchiveAutomationWorkflowRequest;
use App\Http\Requests\DisableAutomationWorkflowRequest;
use App\Http\Requests\DuplicateAutomationWorkflowRequest;
use App\Http\Requests\EnableAutomationWorkflowRequest;
use App\Http\Requests\PublishAutomationWorkflowRequest;
use App\Http\Requests\RestoreAutomationWorkflowRequest;
use App\Models\AutomationWorkflow;
use App\Services\Automations\ArchiveAutomationWorkflowService;
use App\Services\Automations\CreateAutomationWorkflowDraftVersionService;
use App\Services\Automations\DisableAutomationWorkflowService;
use App\Services\Automations\DuplicateAutomationWorkflowService;
use App\Services\Automations\EnableAutomationWorkflowService;
use App\Services\Automations\PublishAutomationWorkflowService;
use App\Services\Automations\RestoreAutomationWorkflowService;
use App\Services\Automations\ValidateAutomationWorkflowVersionService;
use Illuminate\Http\JsonResponse;

final class AutomationWorkflowActionController extends Controller
{
    private function owned(AutomationWorkflow $w, TenantContext $c): void
    {
        abort_unless($w->tenant_id === $c->id(), 404);
    }

    public function validateWorkflow(AutomationWorkflow $workflow, TenantContext $c, ValidateAutomationWorkflowVersionService $service): JsonResponse
    {
        $this->owned($workflow, $c);
        $this->authorize('update', $workflow);
        abort_unless($workflow->currentDraftVersion, 422, 'No active draft exists.');

        return response()->json($service->validate($workflow->currentDraftVersion, true));
    }

    public function draft(PublishAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, CreateAutomationWorkflowDraftVersionService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('update', $workflow);

        $result = $s->create($workflow, CreateAutomationWorkflowDraftVersionData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result, 201) : redirect()->route('tenant.automations.edit', $workflow)->with('status', 'Draft version created.');
    }

    public function publish(PublishAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, PublishAutomationWorkflowService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('publish', $workflow);

        $result = $s->publish($workflow, PublishAutomationWorkflowData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Workflow published.');
    }

    public function enable(EnableAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, EnableAutomationWorkflowService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('enable', $workflow);

        $result = $s->enable($workflow, EnableAutomationWorkflowData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Workflow enabled.');
    }

    public function disable(DisableAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, DisableAutomationWorkflowService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('disable', $workflow);

        $result = $s->disable($workflow, DisableAutomationWorkflowData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Workflow disabled.');
    }

    public function duplicate(DuplicateAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, DuplicateAutomationWorkflowService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('duplicate', $workflow);

        $result = $s->duplicate($workflow, DuplicateAutomationWorkflowData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result, 201) : redirect()->route('tenant.automations.show', $result)->with('status', 'Workflow duplicated.');
    }

    public function archive(ArchiveAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, ArchiveAutomationWorkflowService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('archive', $workflow);

        $result = $s->archive($workflow, ArchiveAutomationWorkflowData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result) : redirect()->route('tenant.automations.index')->with('status', 'Workflow archived.');
    }

    public function restore(RestoreAutomationWorkflowRequest $r, AutomationWorkflow $workflow, TenantContext $c, RestoreAutomationWorkflowService $s)
    {
        $this->owned($workflow, $c);
        $this->authorize('restore', $workflow);

        $result = $s->restore($workflow, RestoreAutomationWorkflowData::from($r->validated()), $r->user());

        return $r->expectsJson() ? response()->json($result) : back()->with('status', 'Workflow restored.');
    }
}
