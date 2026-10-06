<?php

namespace App\Services;

use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WhatsAppAttachmentService
{
    public function store(WhatsAppMessage $message, UploadedFile $file, string $category): WhatsAppMessageAttachment
    {
        if (! $file->isValid() || $file->getSize() < 1 || $file->getSize() > config('whatsapp_messages.attachment_max_kb') * 1024) {
            throw ValidationException::withMessages(['attachment' => 'The attachment is empty, invalid, or too large.']);
        }
        $mime = (string) $file->getMimeType();
        $extension = config("whatsapp_messages.mimes.{$category}.{$mime}");
        if (! is_string($extension)) {
            throw ValidationException::withMessages(['attachment' => 'This file type is not supported for the selected message type.']);
        }
        $disk = config('whatsapp_messages.attachment_disk');
        $key = "whatsapp/{$message->tenant_id}/{$message->uuid}/".Str::random(40).".{$extension}";
        $stream = fopen($file->getRealPath(), 'rb');
        Storage::disk($disk)->put($key, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        $original = str($file->getClientOriginalName())->basename()->limit(255)->toString();

        return WhatsAppMessageAttachment::create(['tenant_id' => $message->tenant_id, 'whatsapp_message_id' => $message->id, 'disk' => $disk,
            'storage_key' => $key, 'original_name' => $original, 'safe_name' => Str::slug(pathinfo($original, PATHINFO_FILENAME)).".{$extension}",
            'mime_type' => $mime, 'extension' => $extension, 'size_bytes' => $file->getSize(), 'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'media_category' => $category]);
    }
}
