<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppMessageQuery;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WhatsAppMessageAttachmentController extends Controller
{
    public function __invoke(string $messageUuid, WhatsAppMessageQuery $query): StreamedResponse
    {
        $attachment = $query->find($messageUuid)->attachment;
        abort_unless($attachment && Storage::disk($attachment->disk)->exists($attachment->storage_key), 404);

        return Storage::disk($attachment->disk)->download($attachment->storage_key, $attachment->safe_name, [
            'Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
