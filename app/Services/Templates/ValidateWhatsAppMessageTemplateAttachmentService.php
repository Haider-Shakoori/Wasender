<?php

namespace App\Services\Templates;

use App\Data\Templates\ValidatedTemplateAttachment;
use App\Enums\WhatsAppMessageTemplateType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ValidateWhatsAppMessageTemplateAttachmentService
{
    public function validate(UploadedFile $file, WhatsAppMessageTemplateType $type): ValidatedTemplateAttachment
    {
        if ($type === WhatsAppMessageTemplateType::Text || ! $file->isValid() || ! is_file($file->getRealPath())) {
            throw ValidationException::withMessages(['attachment' => 'A valid media file is required.']);
        }
        $size = (int) $file->getSize();
        if ($size < 1) {
            throw ValidationException::withMessages(['attachment' => 'Empty files are not allowed.']);
        }
        $category = $type->value;
        $limit = config("whatsapp_message_templates.attachment_limits_mb.{$category}") * 1024 * 1024;
        if ($size > $limit) {
            throw ValidationException::withMessages(['attachment' => 'The attachment exceeds the configured size limit.']);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! is_string($mime) || ! in_array($mime, config("whatsapp_message_templates.attachment_mimes.{$category}", []), true)) {
            throw ValidationException::withMessages(['attachment' => 'Detected MIME type is not allowed for this template type.']);
        }
        $checksum = hash_file('sha256', $file->getRealPath());
        if (! $checksum) {
            throw ValidationException::withMessages(['attachment' => 'Attachment checksum could not be calculated.']);
        }
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf', 'text/plain' => 'txt', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx', 'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'video/mp4' => 'mp4'];
        $extension = $extensions[$mime];
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'attachment';

        return new ValidatedTemplateAttachment($mime, $category, $size, $checksum, $extension, Str::limit($base, 150, '').'.'.$extension);
    }
}
