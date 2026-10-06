<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class WhatsAppConversationMentionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $conversationUuid, private readonly string $noteUuid, private readonly string $authorName, private readonly string $preview) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => 'You were mentioned in an internal note on a WhatsApp conversation.', 'conversation_uuid' => $this->conversationUuid, 'note_uuid' => $this->noteUuid, 'author_name' => $this->authorName, 'preview' => $this->preview];
    }
}
