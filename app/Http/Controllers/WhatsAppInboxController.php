<?php

namespace App\Http\Controllers;

use App\Contracts\TenantContext;
use App\Models\WhatsAppConversation;
use Illuminate\View\View;

final class WhatsAppInboxController extends Controller
{
    public function __invoke(TenantContext $context): View
    {
        $base = WhatsAppConversation::forTenant($context->id());
        $counters = ['unread' => (clone $base)->where('unread_count', '>', 0)->count(), 'mine' => (clone $base)->where('assigned_user_id', auth()->id())->whereIn('status', ['open', 'pending'])->count(), 'unassigned' => (clone $base)->whereNull('assigned_user_id')->whereIn('status', ['open', 'pending'])->count(), 'open' => (clone $base)->where('status', 'open')->count(), 'pending' => (clone $base)->where('status', 'pending')->count(), 'priority' => (clone $base)->whereIn('priority', ['high', 'urgent'])->count()];

        return view('tenant.inbox.overview', compact('counters'));
    }
}
