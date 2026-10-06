<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\WhatsAppCampaign;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class WhatsAppCampaignAttachmentController extends Controller
{
    public function __invoke(WhatsAppCampaign $campaign, TenantContext $ctx): StreamedResponse
    {
        abort_unless($campaign->tenant_id === $ctx->id(), 404);
        $this->authorize('viewContent', $campaign);
        $a = $campaign->attachment()->firstOrFail();
        abort_unless($a->tenant_id === $ctx->id() && Storage::disk($a->disk)->exists($a->storage_key), 404);

        return Storage::disk($a->disk)->download($a->storage_key, $a->safe_name, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => 'application/octet-stream']);
    }
}
