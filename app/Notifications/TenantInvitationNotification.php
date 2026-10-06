<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TenantInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $inviterName,
        private readonly string $tenantName,
        private readonly string $roleName,
        private readonly string $expiresAt,
        private readonly string $acceptUrl,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to join {$this->tenantName}")
            ->greeting('You have a workspace invitation')
            ->line("{$this->inviterName} invited you to join {$this->tenantName} as {$this->roleName}.")
            ->line("This invitation expires {$this->expiresAt}.")
            ->action('Review invitation', $this->acceptUrl)
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
