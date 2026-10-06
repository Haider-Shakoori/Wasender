<?php

namespace App\Services\Campaigns;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WhatsAppCampaignAttachmentService
{
    public function store(WhatsAppCampaign $c, UploadedFile $file): WhatsAppCampaignAttachment
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        $allowed = ['image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image', 'application/pdf' => 'document', 'text/plain' => 'document', 'audio/mpeg' => 'audio', 'audio/ogg' => 'audio', 'video/mp4' => 'video'];
        if (! isset($allowed[$mime]) || $allowed[$mime] !== $c->message_type->value) {
            throw ValidationException::withMessages(['attachment' => 'Attachment MIME type does not match the selected message type.']);
        }
        $disk = config('whatsapp_campaigns.attachment_disk');
        $ext = $file->guessExtension() ?: 'bin';
        $key = "campaigns/{$c->tenant_id}/{$c->uuid}/".Str::uuid().".{$ext}";
        Storage::disk($disk)->put($key, file_get_contents($file->getRealPath()));

        return WhatsAppCampaignAttachment::create(['tenant_id' => $c->tenant_id, 'whatsapp_campaign_id' => $c->id, 'disk' => $disk, 'storage_key' => $key, 'original_name' => $file->getClientOriginalName(), 'safe_name' => Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).".{$ext}", 'mime_type' => $mime, 'extension' => $ext, 'size_bytes' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath()), 'media_category' => $allowed[$mime]]);
    }

    public function replace(WhatsAppCampaign $c, UploadedFile $file): WhatsAppCampaignAttachment
    {
        if ($old = $c->attachment) {
            $old->delete();
        }

        return $this->store($c, $file);
    }
}
