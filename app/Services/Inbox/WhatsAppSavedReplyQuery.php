<?php

namespace App\Services\Inbox;

use App\Contracts\TenantContext;
use App\Models\WhatsAppSavedReply;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class WhatsAppSavedReplyQuery
{
    public function __construct(private TenantContext $context) {}

    public function paginate(array $f): LengthAwarePaginator
    {
        return WhatsAppSavedReply::withTrashed()->forTenant($this->context->id())->when($f['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$v.'%')->orWhere('body', 'like', '%'.$v.'%')))->when($f['shortcut'] ?? null, fn ($q, $v) => $q->where('shortcut', $v))->when(($f['status'] ?? null) === 'active', fn ($q) => $q->whereNull('deleted_at')->where('is_active', true))->when(($f['status'] ?? null) === 'archived', fn ($q) => $q->onlyTrashed())->orderBy('name')->paginate(min(100, max(1, (int) ($f['per_page'] ?? 25))))->withQueryString();
    }
}
