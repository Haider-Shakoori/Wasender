<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppChatbot;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformWhatsAppChatbotController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = WhatsAppChatbot::query()->with(['tenant:id,uuid,name', 'sessions:id,uuid,name'])->withCount(['rules', 'executions as recent_executions_count' => fn ($q) => $q->where('processed_at', '>=', now()->subDays(30)), 'executions as failure_count' => fn ($q) => $q->where('status', 'failed')])->withMax('executions', 'processed_at');
        $query->when($request->string('search')->trim()->toString(), fn ($q, $v) => $q->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($v, '%_').'%')->orWhereHas('tenant', fn ($t) => $t->where('name', 'like', '%'.addcslashes($v, '%_').'%'))));

        return view('platform.chatbots.index', ['chatbots' => $query->latest('updated_at')->paginate(25)->withQueryString()]);
    }
}
