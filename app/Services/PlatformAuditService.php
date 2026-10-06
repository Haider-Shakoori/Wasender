<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class PlatformAuditService
{
    public function record(string $action, ?User $actor, ?Model $subject = null, array $metadata = []): PlatformAuditLog
    {
        return PlatformAuditLog::query()->create([
            'actor_id' => $actor?->id, 'action' => $action,
            'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            'metadata' => $this->sanitize($metadata), 'created_at' => now(),
        ]);
    }

    private function sanitize(array $metadata): array
    {
        return collect($metadata)->except(['password', 'token', 'secret', 'payload', 'exception'])->all();
    }
}
