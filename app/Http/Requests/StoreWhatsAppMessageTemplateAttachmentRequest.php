<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWhatsAppMessageTemplateAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['attachment' => ['required', 'file', 'max:'.(max(config('whatsapp_message_templates.attachment_limits_mb')) * 1024)], 'expected_version' => ['required', 'integer', 'min:1'], 'tenant_id' => ['prohibited'], 'template_version_id' => ['prohibited'], 'disk' => ['prohibited'], 'storage_key' => ['prohibited'], 'checksum_sha256' => ['prohibited'], 'mime_type' => ['prohibited'], 'size_bytes' => ['prohibited']];
    }
}
