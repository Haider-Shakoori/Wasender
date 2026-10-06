<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditService
{
    public function record(Request $request, string $action, ?Model $subject = null, array $before = [], array $after = []): void
    {
        AuditLog::create([
            'tenant_id' => $request->user()?->last_active_tenant_id,
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => str($request->userAgent())->limit(1000),
            'before_values' => $this->redact($before),
            'after_values' => $this->redact($after),
        ]);
    }

    public function recordDomain(
        string $action,
        ?User $user = null,
        ?Tenant $tenant = null,
        ?Model $subject = null,
        array $metadata = [],
        array $before = [],
        array $after = [],
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => $tenant?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before_values' => $this->redact($before),
            'after_values' => $this->redact($after),
            'metadata' => $this->redact($metadata),
        ]);
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|cookie|csrf|session|api.?key/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
