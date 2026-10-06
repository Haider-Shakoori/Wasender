<?php

namespace App\Services\Integrations;

use App\Models\Contact;
use App\Models\Integration;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\PhoneNumberNormalizer;
use App\Services\Templates\CreateTransactionalWhatsAppMessageFromTemplateService;
use App\Services\WhatsAppMessageService;
use Illuminate\Validation\ValidationException;

final class ProcessIntegrationMessageService
{
    public function __construct(private PhoneNumberNormalizer $phones, private WhatsAppMessageService $messages, private CreateTransactionalWhatsAppMessageFromTemplateService $templates, private PublishIntegrationMessageWebhookService $webhooks) {}

    public function send(Integration $integration, User $actor, array $data): WhatsAppMessage
    {
        $phone = $this->phones->normalize($data['recipient']);
        $contact = Contact::firstOrCreate(['tenant_id' => $integration->tenant_id, 'phone_normalized' => ltrim($phone->e164, '+')], ['first_name' => $data['contact']['first_name'] ?? null, 'last_name' => $data['contact']['last_name'] ?? null, 'display_name' => $data['contact']['name'] ?? null, 'phone_input' => $data['recipient'], 'whatsapp_address' => $phone->whatsappAddress, 'status' => 'active', 'consent_status' => 'unknown', 'source' => 'transactional_message', 'source_reference' => $integration->uuid, 'created_by' => $actor->id]);
        if ($contact->opted_out_at || $contact->suppressed_at || $contact->blocked_at) {
            throw ValidationException::withMessages(['recipient' => 'This contact cannot receive messages.']);
        }
        $sessionUuid = $data['session_uuid'] ?? data_get($integration->configuration, 'default_session_uuid');
        $key = 'integration:'.$integration->uuid.':'.$data['idempotency_key'];

        $metadata = ['source' => 'integration', 'integration_uuid' => $integration->uuid];
        $message = isset($data['template_uuid'])
            ? $this->templates->create($integration->tenant, $actor, [
                'session_uuid' => $sessionUuid,
                'recipient' => $data['recipient'],
                'template_uuid' => $data['template_uuid'],
                'template_version_uuid' => $data['template_version_uuid'] ?? null,
                'values' => $data['variables'] ?? [],
                'timezone' => $integration->tenant->timezone,
                'idempotency_key' => $key,
                'metadata' => $metadata,
            ])
            : $this->messages->create($integration->tenant, $actor, [
                'session_uuid' => $sessionUuid,
                'recipient' => $data['recipient'],
                'message_type' => 'text',
                'body' => $data['text'],
                'idempotency_key' => $key,
                'metadata' => $metadata,
            ], null);

        try {
            $this->webhooks->publish($message);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $message;
    }
}
