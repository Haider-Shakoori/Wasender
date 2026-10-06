<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppCampaignAttachment;
use App\Models\WhatsAppCampaignDispatchAttempt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InternalWhatsAppCampaignAttachmentController
{
    public function __invoke(Request $request, string $attachmentUuid): StreamedResponse
    {
        $bearer = $request->bearerToken();
        abort_unless(is_string($bearer) && $bearer !== '', 401);
        try {
            $claims = json_decode(Crypt::decryptString($bearer), true, 16, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            abort(401);
        }
        abort_unless(
            is_array($claims)
            && ($claims['purpose'] ?? null) === 'campaign-attachment'
            && (int) ($claims['expires_at'] ?? 0) >= now()->timestamp
            && hash_equals((string) ($claims['attachment_uuid'] ?? ''), $attachmentUuid),
            401
        );
        $attachment = WhatsAppCampaignAttachment::where('uuid', $attachmentUuid)->firstOrFail();
        $attempt = WhatsAppCampaignDispatchAttempt::where('uuid', $claims['attempt_uuid'] ?? '')->firstOrFail();
        abort_unless(
            $attachment->tenant_id === (int) $claims['tenant_id']
            && $attachment->whatsapp_campaign_id === (int) $claims['campaign_id']
            && $attempt->tenant_id === $attachment->tenant_id
            && $attempt->whatsapp_campaign_id === $attachment->whatsapp_campaign_id
            && $attempt->campaign_execution_id === (int) $claims['execution_id']
            && hash_equals($attachment->checksum_sha256, (string) ($claims['checksum'] ?? '')),
            404
        );
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->storage_key), 404);

        return response()->stream(function () use ($disk, $attachment): void {
            $stream = $disk->readStream($attachment->storage_key);
            abort_unless(is_resource($stream), 404);
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Length' => (string) $attachment->size_bytes,
            'Content-Disposition' => 'attachment; filename="'.addslashes($attachment->safe_name).'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
