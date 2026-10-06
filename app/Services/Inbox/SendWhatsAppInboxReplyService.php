<?php

namespace App\Services\Inbox;

use App\Enums\WhatsAppConversationStatus;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppInboxMessage;
use App\Models\WhatsAppMessage;
use App\Services\Templates\CreateTransactionalWhatsAppMessageFromTemplateService;
use App\Services\WhatsAppMessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SendWhatsAppInboxReplyService
{
    public function __construct(private WhatsAppMessageService $messages, private CreateTransactionalWhatsAppMessageFromTemplateService $templates, private ConversationActivityRecorder $activity) {}

    public function send(WhatsAppConversation $conversation, User $actor, array $data, ?UploadedFile $file): WhatsAppMessage
    {
        if ($conversation->status === WhatsAppConversationStatus::Archived) {
            throw ValidationException::withMessages(['reply' => 'Restore this conversation before replying.']);
        }
        if (! filled($data['template_uuid'] ?? null) && $data['message_type'] === 'text' && ! filled($data['body'] ?? null)) {
            throw ValidationException::withMessages(['body' => 'Enter a reply.']);
        }
        $payload = ['session_uuid' => $conversation->session->uuid, 'recipient' => $conversation->normalized_phone, 'idempotency_key' => $data['idempotency_key']];
        $message = filled($data['template_uuid'] ?? null)
            ? $this->templates->create($conversation->tenant, $actor, $payload + ['template_uuid' => $data['template_uuid'], 'values' => $data['values'] ?? [], 'timezone' => $conversation->tenant->timezone])
            : $this->messages->create($conversation->tenant, $actor, $payload + ['message_type' => $data['message_type'], 'body' => $data['body'] ?? null, 'metadata' => ['inbox_conversation_uuid' => $conversation->uuid]], $file);
        DB::transaction(function () use ($conversation, $actor, $data, $message): void {
            if ($replyUuid = $data['reply_to_message_uuid'] ?? null) {
                $reply = WhatsAppInboxMessage::where('tenant_id', $conversation->tenant_id)->where('whatsapp_conversation_id', $conversation->id)->where('uuid', $replyUuid)->first();
                $message->inboxProjection()->update(['reply_to_message_id' => $reply?->id, 'reply_to_whatsapp_message_id' => $reply?->whatsapp_message_id]);
            }
            $conversation->update(['last_agent_activity_at' => now()]);
            $this->activity->record($conversation, $actor, 'reply_queued', [], $message->uuid);
            if (filled($data['saved_reply_uuid'] ?? null)) {
                $this->activity->record($conversation, $actor, 'saved_reply_used', [], $data['saved_reply_uuid']);
            }
        });

        return $message;
    }
}
