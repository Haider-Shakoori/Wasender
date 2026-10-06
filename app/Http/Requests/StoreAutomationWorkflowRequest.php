<?php

namespace App\Http\Requests;

use App\Enums\AutomationStepType;
use App\Enums\AutomationTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutomationWorkflowRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings', []);
        if (is_array($settings)) {
            foreach (['maximum_execution_minutes', 'maximum_steps', 'maximum_active_executions_per_contact'] as $key) {
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (int) $settings[$key];
                }
            }
            if (array_key_exists('allow_reentry', $settings)) {
                $settings['allow_reentry'] = filter_var($settings['allow_reentry'], FILTER_VALIDATE_BOOL);
            }
            $this->merge(['settings' => $settings]);
        }
        if ($this->filled('steps_json')) {
            $steps = json_decode((string) $this->input('steps_json'), true);
            $this->merge(['steps' => is_array($steps) ? $steps : []]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:'.config('automations.max_name_length')], 'description' => ['nullable', 'string', 'max:'.config('automations.max_description_length')], 'trigger_type' => ['required', Rule::in(AutomationTriggerType::values())], 'trigger_configuration' => ['sometimes', 'array'], 'settings' => ['sometimes', 'array'], 'steps' => ['sometimes', 'array', 'max:'.config('automations.max_steps')], 'steps.*.step_key' => ['required', 'string', 'max:80', 'regex:/\A[a-z][a-z0-9_-]*\z/', 'distinct'], 'steps.*.parent_step_key' => ['nullable', 'string', 'max:80'], 'steps.*.step_type' => ['required', Rule::in(AutomationStepType::values())], 'steps.*.position' => ['required', 'integer', 'min:1', 'max:'.config('automations.max_steps'), 'distinct'], 'steps.*.name' => ['nullable', 'string', 'max:150'], 'steps.*.configuration' => ['sometimes', 'array'], 'tenant_id' => ['prohibited'], 'status' => ['prohibited'], 'is_enabled' => ['prohibited'], 'current_draft_version_id' => ['prohibited'], 'current_published_version_id' => ['prohibited'], 'published_at' => ['prohibited'], 'enabled_at' => ['prohibited'], 'disabled_at' => ['prohibited'], 'created_by' => ['prohibited'], 'updated_by' => ['prohibited'], 'version_number' => ['prohibited']];
    }
}
