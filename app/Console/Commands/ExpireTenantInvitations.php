<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TenantInvitationStatus;
use App\Models\Invitation;
use Illuminate\Console\Command;

final class ExpireTenantInvitations extends Command
{
    protected $signature = 'tenant-invitations:expire';

    protected $description = 'Mark pending tenant invitations past their expiration as expired';

    public function handle(): int
    {
        $count = 0;
        Invitation::query()->where('status', TenantInvitationStatus::Pending)->where('expires_at', '<=', now())
            ->select('id')->chunkById(500, function ($invitations) use (&$count): void {
                $ids = $invitations->pluck('id');
                $count += Invitation::query()->whereKey($ids)->where('status', TenantInvitationStatus::Pending)
                    ->update(['status' => TenantInvitationStatus::Expired, 'updated_at' => now()]);
            });
        $this->info("Expired {$count} tenant invitation(s).");

        return self::SUCCESS;
    }
}
