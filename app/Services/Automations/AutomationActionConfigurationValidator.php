<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Models\AutomationWorkflowStep;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Models\WhatsAppSession;
use Illuminate\Support\Str;

final class AutomationActionConfigurationValidator
{
    public function __construct(private AutomationActionRegistry $registry, private TenantContext $tenant) {}

    public function validate(AutomationWorkflowStep $step, bool $publishing): array
    {
        $c = $step->configuration ?? [];
        $type = (string) ($c['action_type'] ?? '');
        $p = $c['parameters'] ?? null;
        $definition = $this->registry->find($type);
        if (! $definition || ! $definition->supported) {
            return [$this->error('unsupported_action', $step)];
        }if (! is_array($p) || array_diff(array_keys($p), $definition->configurationFields) !== []) {
            return [$this->error('invalid_action_configuration', $step)];
        }$errors = [];
        if (in_array($type, ['add_contact_label', 'remove_contact_label'], true) && (! $this->uuid($p['label_uuid'] ?? null) || ! ContactLabel::where('tenant_id', $this->tenant->id())->where('uuid', $p['label_uuid'] ?? '')->exists())) {
            $errors[] = $this->error('tenant_reference_invalid', $step);
        }if (in_array($type, ['add_contact_to_group', 'remove_contact_from_group'], true) && (! $this->uuid($p['group_uuid'] ?? null) || ! ContactGroup::where('tenant_id', $this->tenant->id())->where('uuid', $p['group_uuid'] ?? '')->where('is_active', true)->exists())) {
            $errors[] = $this->error('tenant_reference_invalid', $step);
        }if ($type === 'send_whatsapp_template') {
            $template = WhatsAppMessageTemplate::forTenant($this->tenant->id())->where('uuid', $p['template_uuid'] ?? '')->whereNull('archived_at')->first();
            if (! $template || ! $template->current_published_version_id) {
                $errors[] = $this->error('template_not_published', $step);
            }if (! in_array($p['session_strategy'] ?? null, ['automatic', 'specific'], true)) {
                $errors[] = $this->error('invalid_action_configuration', $step);
            }if (($p['session_strategy'] ?? null) === 'specific' && (! $this->uuid($p['session_uuid'] ?? null) || ! WhatsAppSession::forTenant($this->tenant->id())->where('uuid', $p['session_uuid'] ?? '')->exists())) {
                $errors[] = $this->error('invalid_action_configuration', $step);
            }if (! is_array($p['variable_mappings'] ?? [])) {
                $errors[] = $this->error('invalid_action_configuration', $step);
            } else {
                foreach ($p['variable_mappings'] as $key => $mapping) {
                    $source = is_array($mapping) ? ($mapping['source'] ?? null) : null;
                    $requiredKey = in_array($source, ['contact', 'trigger', 'context'], true);
                    if (! is_string($key) || ! is_array($mapping) || ! in_array($source, ['contact', 'trigger', 'context', 'constant'], true) || array_diff(array_keys($mapping), ['source', 'key', 'value']) !== [] || ($requiredKey && ! is_string($mapping['key'] ?? null)) || ($source === 'constant' && (! array_key_exists('value', $mapping) || (! is_scalar($mapping['value']) && $mapping['value'] !== null)))) {
                        $errors[] = $this->error('invalid_action_configuration', $step);
                    }
                }
            }if ($publishing && (! $this->uuid($p['template_version_uuid'] ?? null) || ! is_int($p['template_version_number'] ?? null) || ! preg_match('/\A[0-9a-f]{64}\z/', $p['template_content_hash'] ?? ''))) {
                $errors[] = $this->error('template_not_frozen', $step);
            } elseif ($publishing && $template) {
                $version = WhatsAppMessageTemplateVersion::where('whatsapp_message_template_id', $template->id)->where('uuid', $p['template_version_uuid'])->where('version_number', $p['template_version_number'])->where('content_hash', $p['template_content_hash'])->first();
                if (! $version) {
                    $errors[] = $this->error('template_not_frozen', $step);
                } else {
                    $configuration = $version->variable_configuration ?? [];
                    foreach (array_keys($p['variable_mappings'] ?? []) as $key) {
                        if (! array_key_exists($key, $configuration)) {
                            $errors[] = $this->error('invalid_action_configuration', $step);
                        }
                    }
                    foreach ($configuration as $key => $variable) {
                        if (($variable['required'] ?? false) && ($variable['default'] ?? null) === null && ! array_key_exists($key, $p['variable_mappings'] ?? [])) {
                            $errors[] = $this->error('template_variable_missing', $step);
                        }
                    }
                }
            }
        }if ($type === 'update_contact') {
            if (! is_array($p['updates'] ?? null) || $p['updates'] === [] || array_diff(array_keys($p['updates']), ['first_name', 'last_name', 'company', 'preferred_language', 'timezone']) !== []) {
                $errors[] = $this->error('invalid_contact_field', $step);
            } else {
                foreach ($p['updates'] as $mapping) {
                    if (! is_array($mapping) || ! in_array($mapping['source'] ?? null, ['contact', 'trigger', 'context', 'constant'], true)) {
                        $errors[] = $this->error('invalid_action_configuration', $step);
                    }
                }
            }
        }if ($type === 'stop_workflow' && (! is_string($p['reason'] ?? null) || mb_strlen($p['reason']) > 120)) {
            $errors[] = $this->error('invalid_action_configuration', $step);
        }

        return $errors;
    }

    private function uuid(mixed $v): bool
    {
        return is_string($v) && Str::isUuid($v);
    }

    private function error(string $code, AutomationWorkflowStep $step): array
    {
        return ['code' => $code, 'field' => 'configuration', 'step_key' => $step->step_key, 'message' => 'Action configuration is invalid.'];
    }
}
