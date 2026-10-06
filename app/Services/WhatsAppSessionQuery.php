<?php

namespace App\Services;

use App\Contracts\TenantContext;
use App\Models\WhatsAppSession;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppSessionQuery
{
    public function __construct(private TenantContext $context) {}

    public function summary(): array
    {
        $base = WhatsAppSession::forTenant($this->context->id());

        return [
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->where('status', 'ready')->count(),
            'attention' => (clone $base)->whereIn('status', ['disconnected', 'failed'])->count(),
            'connecting' => (clone $base)->whereIn('status', ['creating', 'initializing', 'qr_pending', 'authenticating', 'authenticated', 'reconnecting'])->count(),
        ];
    }

    public function paginate(): LengthAwarePaginator
    {
        return WhatsAppSession::forTenant($this->context->id())->with('creator:id,name')->latest()->paginate(20);
    }

    public function find(string $uuid): WhatsAppSession
    {
        return WhatsAppSession::forTenant($this->context->id())->with(['creator:id,name', 'events' => fn ($query) => $query->latest('occurred_at')->limit(30)])->where('uuid', $uuid)->firstOrFail();
    }
}
