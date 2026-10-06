<?php

namespace App\Services\Templates;

use App\Contracts\TenantContext;
use App\Contracts\TenantEntitlements;
use App\Data\Templates\StoreWhatsAppMessageTemplateAttachmentData;
use App\Enums\WhatsAppMessageTemplateVersionStatus;
use App\Models\User;
use App\Models\WhatsAppMessageTemplate;
use App\Models\WhatsAppMessageTemplateAttachment;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StoreWhatsAppMessageTemplateAttachmentService
{
    public function __construct(private TenantContext $context, private TenantEntitlements $entitlements, private WhatsAppMessageTemplateLifecycleGuard $guard, private ValidateWhatsAppMessageTemplateAttachmentService $validator, private ValidateWhatsAppMessageTemplateVersionService $templateValidator, private AuditService $audit) {}

    public function store(WhatsAppMessageTemplate $template, StoreWhatsAppMessageTemplateAttachmentData $data, User $actor): WhatsAppMessageTemplateAttachment
    {
        $this->entitlements->requireFeature('whatsapp_message_templates');
        $this->guard->editable($template);
        $validated = $this->validator->validate($data->file, $template->type);
        $disk = config('whatsapp_message_templates.attachment_disk');
        $key = "message-templates/{$template->tenant_id}/{$template->uuid}/".Str::uuid().'.'.$validated->extension;
        $stream = fopen($data->file->getRealPath(), 'rb');
        $stored = $stream !== false && Storage::disk($disk)->put($key, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (! $stored || ! Storage::disk($disk)->exists($key)) {
            Storage::disk($disk)->delete($key);
            throw ValidationException::withMessages(['attachment' => 'Private attachment storage failed.']);
        }
        $verifyStream = Storage::disk($disk)->readStream($key);
        $verifyHash = $verifyStream ? hash_init('sha256') : false;
        if ($verifyHash) {
            hash_update_stream($verifyHash, $verifyStream);
            $verifiedChecksum = hash_final($verifyHash);
            fclose($verifyStream);
        } else {
            $verifiedChecksum = null;
        }
        if ($verifiedChecksum !== $validated->checksum || Storage::disk($disk)->size($key) !== $validated->sizeBytes) {
            Storage::disk($disk)->delete($key);
            throw ValidationException::withMessages(['attachment' => 'Stored attachment verification failed.']);
        }
        $old = null;
        try {
            $attachment = DB::transaction(function () use ($template, $data, $actor, $validated, $disk, $key, &$old): WhatsAppMessageTemplateAttachment {
                $template = WhatsAppMessageTemplate::forTenant($this->context->id())->whereKey($template->id)->lockForUpdate()->firstOrFail();
                $this->guard->editable($template);
                $this->guard->expected($template, $data->expectedVersion);
                $version = $template->currentDraftVersion;
                if (! $version || $version->status !== WhatsAppMessageTemplateVersionStatus::Draft) {
                    throw ValidationException::withMessages(['attachment' => 'An active draft is required.']);
                }
                $old = $version->attachment;
                $attachment = new WhatsAppMessageTemplateAttachment;
                $attachment->forceFill(['tenant_id' => $template->tenant_id, 'whatsapp_message_template_id' => $template->id, 'whatsapp_message_template_version_id' => $version->id, 'disk' => $disk, 'storage_key' => $key, 'original_name' => Str::limit(basename($data->file->getClientOriginalName()), 255, ''), 'safe_name' => $validated->safeName, 'mime_type' => $validated->mimeType, 'extension' => $validated->extension, 'size_bytes' => $validated->sizeBytes, 'checksum_sha256' => $validated->checksum, 'media_category' => $validated->mediaCategory, 'created_by' => $actor->id])->save();
                if ($old) {
                    $old->delete();
                }
                $version->setRelation('attachment', $attachment);
                $result = $this->templateValidator->validate($version, $template->type);
                $version->forceFill(['content_hash' => $result->contentHash])->save();
                $template->forceFill(['updated_by' => $actor->id, 'lock_version' => $template->lock_version + 1])->save();
                $this->audit->recordDomain($old ? 'whatsapp_message_template.attachment_replaced' : 'whatsapp_message_template.attachment_added', $actor, $template->tenant, $template, ['template_uuid' => $template->uuid, 'template_version_uuid' => $version->uuid, 'attachment_uuid' => $attachment->uuid, 'media_category' => $attachment->media_category, 'mime_type' => $attachment->mime_type, 'size_bytes' => $attachment->size_bytes]);

                return $attachment;
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($key);
            throw $e;
        }
        if ($old) {
            $this->deleteIfUnreferenced($old);
        }

        return $attachment;
    }

    public function deleteIfUnreferenced(WhatsAppMessageTemplateAttachment $attachment): void
    {
        if (! WhatsAppMessageTemplateAttachment::where('storage_key', $attachment->storage_key)->where('id', '!=', $attachment->id)->exists()) {
            Storage::disk($attachment->disk)->delete($attachment->storage_key);
        }
    }
}
