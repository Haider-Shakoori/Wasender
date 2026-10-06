<?php

namespace App\Services;

use App\Contracts\TenantEntitlements;
use App\Data\Templates\RenderedWhatsAppTemplatePayload;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Enums\WhatsAppSessionStatus;
use App\Jobs\DispatchWhatsAppMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageAttachment;
use App\Models\WhatsAppMessageEvent;
use App\Models\WhatsAppMessageTemplateVersion;
use App\Models\WhatsAppSession;
use App\Services\Inbox\LinkOutboundWhatsAppMessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WhatsAppMessageService
{
    public function __construct(private TenantEntitlements $entitlements, private WhatsAppRecipientNormalizer $normalizer, private WhatsAppAttachmentService $attachments, private AuditService $audit, private LinkOutboundWhatsAppMessageService $inbox) {}

    public function create(Tenant $tenant, User $actor, array $data, ?UploadedFile $file, ?WhatsAppMessageTemplateVersion $templateVersion = null, ?RenderedWhatsAppTemplatePayload $rendered = null): WhatsAppMessage
    {
        $this->entitlements->requireFeature('messages.send');
        $this->entitlements->requireCapacity('messages.monthly');
        $session = WhatsAppSession::forTenant($tenant)->where('uuid', $data['session_uuid'])->where('status', WhatsAppSessionStatus::Ready)->firstOrFail();
        $type = WhatsAppMessageType::from($data['message_type']);
        $body = isset($data['body']) ? trim($data['body']) : null;
        if ($type === WhatsAppMessageType::Text && $file) {
            throw ValidationException::withMessages(['attachment' => 'Text messages cannot include an attachment.']);
        }
        if ($type !== WhatsAppMessageType::Text && ! $file && ! $templateVersion?->attachment) {
            throw ValidationException::withMessages(['attachment' => 'Select one attachment for a media message.']);
        }
        $recipient = $this->normalizer->normalize($data['recipient']);
        $checksum = $file ? hash_file('sha256', $file->getRealPath()) : $templateVersion?->attachment?->checksum_sha256;
        $payloadHash = hash('sha256', json_encode([$session->uuid, $recipient, $type->value, $body, $checksum, $rendered?->templateVersionUuid, $rendered?->renderHash], JSON_THROW_ON_ERROR));
        $existing = WhatsAppMessage::where('tenant_id', $tenant->id)->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing) {
            if (! hash_equals($existing->payload_hash, $payloadHash)) {
                throw ValidationException::withMessages(['idempotency_key' => 'This submission key was already used for different content.']);
            }

            return $existing;
        }

        return DB::transaction(function () use ($tenant, $actor, $session, $data, $type, $body, $recipient, $payloadHash, $file, $templateVersion, $rendered): WhatsAppMessage {
            $tenant->currentSubscription()->lockForUpdate()->first();
            $tenant->unsetRelation('currentSubscription');
            $this->entitlements->requireFeature('messages.send');
            $this->entitlements->requireCapacity('messages.monthly');
            $message = WhatsAppMessage::create(['tenant_id' => $tenant->id, 'whatsapp_session_id' => $session->id, 'created_by' => $actor->id,
                'recipient' => trim($data['recipient']), 'recipient_normalized' => $recipient, 'message_type' => $type, 'body' => $body,
                'status' => WhatsAppMessageStatus::Queued, 'idempotency_key' => $data['idempotency_key'], 'payload_hash' => $payloadHash,
                'message_template_id' => $templateVersion?->whatsapp_message_template_id, 'message_template_version_id' => $templateVersion?->id,
                'template_uuid' => $rendered?->templateUuid, 'template_version_uuid' => $rendered?->templateVersionUuid, 'template_version_number' => $rendered?->templateVersionNumber,
                'template_content_hash' => $rendered?->templateContentHash, 'template_render_hash' => $rendered?->renderHash,
                'template_variable_values' => $rendered?->resolvedVariables, 'template_rendered_at' => $rendered?->renderedAt,
                'metadata' => $data['metadata'] ?? null,
                'queued_at' => now(), 'max_attempts' => config('whatsapp_messages.max_attempts'), 'expires_at' => now()->addMinutes(config('whatsapp_messages.expires_minutes'))]);
            if ($file) {
                $this->attachments->store($message, $file, $type->value);
            } elseif ($media = $templateVersion?->attachment) {
                WhatsAppMessageAttachment::create(['tenant_id' => $tenant->id, 'whatsapp_message_id' => $message->id, 'disk' => $media->disk, 'storage_key' => $media->storage_key, 'original_name' => $media->original_name, 'safe_name' => $media->safe_name, 'mime_type' => $media->mime_type, 'extension' => $media->extension, 'size_bytes' => $media->size_bytes, 'checksum_sha256' => $media->checksum_sha256, 'media_category' => $media->media_category, 'width' => $media->width, 'height' => $media->height, 'duration_seconds' => $media->duration_seconds]);
            }
            WhatsAppMessageEvent::create(['tenant_id' => $tenant->id, 'whatsapp_message_id' => $message->id, 'event' => 'message.queued', 'to_status' => 'queued', 'source' => 'user', 'occurred_at' => now(), 'created_at' => now()]);
            $this->inbox->link($message);
            $this->audit->recordDomain('whatsapp.message_created', $actor, $tenant, $message, ['message_uuid' => $message->uuid, 'type' => $type->value]);
            DispatchWhatsAppMessage::dispatch($message->id)->afterCommit();

            return $message;
        }, 3);
    }
}
