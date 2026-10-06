<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhatsAppMessageCallbackController extends Controller
{
    public function __invoke(Request $request, WhatsAppMessageLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate(['event_id' => ['required', 'uuid'], 'message_uuid' => ['required', 'uuid'], 'request_id' => ['required', 'uuid'],
            'event' => ['required', 'in:sent,delivered,read,failed'], 'whatsapp_message_id' => ['nullable', 'string', 'max:191'],
            'failure_code' => ['nullable', 'string', 'max:80'], 'message' => ['nullable', 'string', 'max:500'], 'retryable' => ['nullable', 'boolean']]);
        $message = WhatsAppMessage::where('uuid', $data['message_uuid'])->where('connector_request_id', $data['request_id'])->firstOrFail();
        if (in_array($data['event'], ['sent', 'delivered', 'read'], true)) {
            $target = WhatsAppMessageStatus::from($data['event']);
            $changes = $target === WhatsAppMessageStatus::Sent ? ['whatsapp_message_id' => $data['whatsapp_message_id'] ?? null] : [];
            $target === WhatsAppMessageStatus::Sent
                ? $lifecycle->transition($message, $target, 'connector', $changes, $data['event_id'])
                : $lifecycle->acknowledge($message, $target, $data['event_id']);
        } elseif (! in_array($message->status, [WhatsAppMessageStatus::Delivered, WhatsAppMessageStatus::Read], true)) {
            $lifecycle->transition($message, WhatsAppMessageStatus::Failed, 'connector', ['failure_code' => $data['failure_code'] ?? 'transport_failure', 'failure_message' => $data['message'] ?? 'Transport failure.', 'failure_retryable' => (bool) ($data['retryable'] ?? false)], $data['event_id']);
        }

        return response()->json(['accepted' => true], 202);
    }
}
