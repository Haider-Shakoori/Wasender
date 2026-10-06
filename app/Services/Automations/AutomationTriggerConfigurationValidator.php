<?php

namespace App\Services\Automations;

use App\Contracts\TenantContext;
use App\Models\ContactGroup;
use App\Models\ContactLabel;
use Carbon\CarbonImmutable;

final class AutomationTriggerConfigurationValidator
{
    public function __construct(private AutomationTriggerRegistry $registry, private TenantContext $context) {}

    public function validate(string $type, array $config, bool $publishing): array
    {
        $errors = [];
        $definition = $this->registry->find($type);
        if (! $definition) {
            return [$this->error('unsupported_trigger', 'trigger_type', 'Unknown trigger type.')];
        }if ($publishing && ! $definition->supported) {
            $errors[] = $this->error('unsupported_in_current_version', 'trigger_type', 'Trigger is unsupported in the current version.');
        }if (array_diff(array_keys($config), $definition->configurationFields) !== []) {
            $errors[] = $this->error('invalid_trigger_configuration', 'trigger_configuration', 'Unknown trigger configuration key.');
        }if ($type === 'contact_updated') {
            if (isset($config['tracked_fields']) && (! is_array($config['tracked_fields']) || count($config['tracked_fields']) > 10 || array_diff($config['tracked_fields'], ['company', 'preferred_language', 'timezone', 'status', 'consent_status']) !== [])) {
                $errors[] = $this->error('invalid_trigger_configuration', 'trigger_configuration.tracked_fields', 'Tracked fields are invalid.');
            }
        }if ($type === 'contact_added_to_group' && (! $this->uuid($config['group_uuid'] ?? null) || ! ContactGroup::where('tenant_id', $this->context->id())->where('uuid', $config['group_uuid'] ?? '')->exists())) {
            $errors[] = $this->error('tenant_reference_invalid', 'trigger_configuration.group_uuid', 'Group reference is invalid.');
        }if ($type === 'contact_label_assigned' && (! $this->uuid($config['label_uuid'] ?? null) || ! ContactLabel::where('tenant_id', $this->context->id())->where('uuid', $config['label_uuid'] ?? '')->exists())) {
            $errors[] = $this->error('tenant_reference_invalid', 'trigger_configuration.label_uuid', 'Label reference is invalid.');
        }if ($type === 'scheduled_datetime') {
            try {
                $timezone = new \DateTimeZone((string) ($config['timezone'] ?? ''));
                $run = CarbonImmutable::createFromFormat('Y-m-d\TH:i:s', (string) ($config['run_at'] ?? ''), $timezone);
                if (! $run || $run->format('Y-m-d\TH:i:s') !== ($config['run_at'] ?? '') || ($publishing && $run->isPast())) {
                    throw new \RuntimeException;
                }
            } catch (\Throwable) {
                $errors[] = $this->error('invalid_trigger_configuration', 'trigger_configuration.run_at', 'A valid future local datetime and IANA timezone are required.');
            }
        }

        return $errors;
    }

    private function uuid(mixed $v): bool
    {
        return is_string($v) && (bool) preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $v);
    }

    private function error(string $code, string $field, string $message): array
    {
        return compact('code', 'field', 'message') + ['step_key' => null];
    }
}
