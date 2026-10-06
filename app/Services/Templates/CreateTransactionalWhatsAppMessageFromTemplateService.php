<?php

namespace App\Services\Templates;

use App\Data\Templates\WhatsAppTemplateRenderContext;
use App\Enums\WhatsAppMessageTemplateStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Services\WhatsAppMessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateTransactionalWhatsAppMessageFromTemplateService
{
    public function __construct(private WhatsAppMessageTemplateRenderer $renderer, private WhatsAppMessageService $messages, private WhatsAppMessageTemplateUsageService $usages) {}

    public function create(Tenant $tenant, User $actor, array $data): WhatsAppMessage
    {
        $requestHash = hash('sha256', json_encode([$data['session_uuid'], trim($data['recipient']), $data['template_uuid'], $data['template_version_uuid'] ?? null, $data['values'] ?? [], $data['timezone'] ?? 'UTC'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $existing = WhatsAppMessage::where('tenant_id', $tenant->id)->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            if (! hash_equals((string) data_get($existing->metadata, 'template_request_hash', ''), $requestHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'This submission key was already used for different content.']);
            }

            return $existing;
        }
        $templateQuery = WhatsAppMessageTemplate::forTenant($tenant)->where('uuid', $data['template_uuid']);
        if (! isset($data['template_version_uuid'])) {
            $templateQuery->where('status', WhatsAppMessageTemplateStatus::Published);
        }
        $template = $templateQuery->firstOrFail();
        $version = isset($data['template_version_uuid'])
            ? WhatsAppMessageTemplateVersion::where('whatsapp_message_template_id', $template->id)->where('uuid', $data['template_version_uuid'])->with('attachment')->firstOrFail()
            : $template->currentPublishedVersion()->with('attachment')->first();
        if (! $version) {
            throw ValidationException::withMessages(['template_uuid' => 'The template has no published version.']);
        }
        $version->setRelation('template', $template);
        $rendered = $this->renderer->render($version, new WhatsAppTemplateRenderContext($version->variable_context, $data['values'] ?? [], $data['timezone'] ?? 'UTC'));
        $body = $rendered->type->value === 'text' ? $rendered->body : $rendered->caption;

        return DB::transaction(function () use ($tenant, $actor, $data, $version, $rendered, $body, $requestHash): WhatsAppMessage {
            $message = $this->messages->create($tenant, $actor, ['session_uuid' => $data['session_uuid'], 'recipient' => $data['recipient'], 'message_type' => $rendered->type->value, 'body' => $body, 'idempotency_key' => $data['idempotency_key'], 'metadata' => ['template_request_hash' => $requestHash]], null, $version, $rendered);
            $this->usages->record($version, 'transactional', $message->uuid);

            return $message;
        });
    }
}
