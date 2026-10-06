<?php

namespace App\Http\Controllers;

use App\Models\IntegrationEvent;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Services\Integrations\ProcessIntegrationMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class IntegrationApiController extends Controller
{
    public function message(Request $request, ProcessIntegrationMessageService $service): JsonResponse
    {
        $integration = $request->attributes->get('integration_model');
        $data = $request->validate(['recipient' => 'required|string|max:40', 'session_uuid' => 'nullable|uuid', 'template_uuid' => 'required_without:text|nullable|uuid', 'template_version_uuid' => 'nullable|uuid', 'variables' => 'nullable|array|max:30', 'variables.*' => 'nullable|string|max:500', 'text' => 'required_without:template_uuid|nullable|string|max:4096', 'idempotency_key' => 'required|string|max:191', 'contact' => 'nullable|array', 'contact.name' => 'nullable|string|max:180', 'contact.first_name' => 'nullable|string|max:100', 'contact.last_name' => 'nullable|string|max:100']);
        $actor = $integration->creator ?? $integration->tenant->owner;
        abort_unless($actor, 422, 'Integration has no sending actor.');
        $message = $service->send($integration, $actor, $data);
        $integration->update(['last_success_at' => now(), 'last_failure_code' => null]);

        return response()->json(['message_uuid' => $message->uuid, 'status' => $message->status->value], 202);
    }

    public function event(Request $request, ProcessIntegrationMessageService $service): JsonResponse
    {
        $integration = $request->attributes->get('integration_model');
        $data = $request->validate(['event_type' => ['required', 'string', Rule::in(config('integrations.inbound_events'))], 'external_event_id' => 'required|string|max:191', 'payload' => 'required|array|max:50']);
        $hash = hash('sha256', json_encode($data['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $event = IntegrationEvent::firstOrCreate(['integration_id' => $integration->id, 'event_type' => $data['event_type'], 'external_event_id' => $data['external_event_id']], ['tenant_id' => $integration->tenant_id, 'status' => 'received', 'payload_hash' => $hash]);
        if (! $event->wasRecentlyCreated) {
            abort_unless(hash_equals($event->payload_hash, $hash), 409, 'Idempotency key reused with a different payload.');

            return response()->json(['event_uuid' => $event->uuid, 'status' => $event->status]);
        }
        try {
            $mapping = data_get($integration->configuration, 'event_templates.'.$data['event_type']);
            if ($mapping) {
                $payload = $data['payload'];
                $recipient = data_get($payload, 'billing.phone') ?? data_get($payload, 'phone');
                abort_unless($recipient, 422, 'Event phone is required.');
                $actor = $integration->creator ?? $integration->tenant->owner;
                $version = WhatsAppMessageTemplateVersion::where('uuid', $mapping['template_version_uuid'])->whereHas('template', fn ($query) => $query->where('tenant_id', $integration->tenant_id))->firstOrFail();
                $variables = array_intersect_key($this->variables($payload), $version->variable_configuration ?? []);
                $message = $service->send($integration, $actor, ['recipient' => $recipient, 'template_uuid' => $mapping['template_uuid'], 'template_version_uuid' => $mapping['template_version_uuid'], 'variables' => $variables, 'idempotency_key' => 'event:'.$event->uuid]);
                $event->update(['status' => 'processed', 'related_message_uuid' => $message->uuid, 'processed_at' => now()]);
            } else {
                $event->update(['status' => 'processed', 'processed_at' => now()]);
            }
            $integration->update(['last_success_at' => now(), 'last_failure_code' => null]);
        } catch (\Throwable $e) {
            report($e);
            $event->update(['status' => 'failed', 'failure_code' => 'event_processing_failed', 'processed_at' => now()]);
            $integration->update(['status' => 'error', 'last_failure_at' => now(), 'last_failure_code' => 'event_processing_failed']);

            return response()->json(['event_uuid' => $event->uuid, 'status' => 'failed'], 422);
        }

        return response()->json(['event_uuid' => $event->uuid, 'status' => $event->status], 202);
    }

    private function variables(array $payload): array
    {
        return array_filter(['customer_name' => data_get($payload, 'billing.first_name') ?: data_get($payload, 'customer_name'), 'order_number' => data_get($payload, 'number') ?? data_get($payload, 'order_number'), 'total' => (string) (data_get($payload, 'total') ?? ''), 'currency' => data_get($payload, 'currency'), 'status' => data_get($payload, 'status')], fn ($v) => $v !== null && $v !== '');
    }
}
