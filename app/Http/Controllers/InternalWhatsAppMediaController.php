<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class InternalWhatsAppMediaController extends Controller
{
    public function __invoke(Request $request, string $messageUuid): StreamedResponse
    {
        $message = WhatsAppMessage::with('attachment')->where('uuid', $messageUuid)->firstOrFail();
        abort_unless($message->connector_request_id && hash_equals($message->connector_request_id, (string) $request->query('request_id')), 403);
        $attachment = $message->attachment;
        abort_unless($attachment, 404);

        return Storage::disk($attachment->disk)->download($attachment->storage_key, $attachment->safe_name, ['Content-Type' => $attachment->mime_type, 'X-Checksum-Sha256' => $attachment->checksum_sha256, 'X-Content-Type-Options' => 'nosniff']);
    }
}
