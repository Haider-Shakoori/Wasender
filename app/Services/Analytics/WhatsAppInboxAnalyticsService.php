<?php

namespace App\Services\Analytics;

use App\Data\Analytics\AnalyticsDateRange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class WhatsAppInboxAnalyticsService
{
    public function report(Tenant $tenant, AnalyticsDateRange $range, int $limit = 10): array
    {
        $conversations = DB::table('whatsapp_conversations')->where('tenant_id', $tenant->id);
        $statuses = (clone $conversations)->select('status', DB::raw('COUNT(*) total'))->groupBy('status')->pluck('total', 'status');
        $messages = DB::table('whatsapp_inbox_messages')->where('tenant_id', $tenant->id)->whereBetween('occurred_at', [$range->from, $range->to]);
        $directions = (clone $messages)->select('direction', DB::raw('COUNT(*) total'))->groupBy('direction')->pluck('total', 'direction');
        $userCounts = fn (string $table, string $userColumn, ?string $activity = null) => DB::table($table)->where("{$table}.tenant_id", $tenant->id)->when($activity, fn ($q) => $q->where('activity_type', $activity))->whereBetween($table === 'whatsapp_conversation_activities' ? 'occurred_at' : 'created_at', [$range->from, $range->to])->join('users', 'users.id', '=', "{$table}.{$userColumn}")->select('users.uuid', 'users.name', DB::raw('COUNT(*) value'))->groupBy('users.id', 'users.uuid', 'users.name')->orderByDesc('value')->limit(AnalyticsMath::limit($limit))->get();

        return [
            'metrics' => [AnalyticsMath::metric('total_conversations', (clone $conversations)->count()), AnalyticsMath::metric('open', (int) ($statuses['open'] ?? 0)), AnalyticsMath::metric('pending', (int) ($statuses['pending'] ?? 0)), AnalyticsMath::metric('closed', (int) ($statuses['closed'] ?? 0)), AnalyticsMath::metric('archived', (int) ($statuses['archived'] ?? 0)), AnalyticsMath::metric('unread', (clone $conversations)->where('unread_count', '>', 0)->count()), AnalyticsMath::metric('assigned', (clone $conversations)->whereNotNull('assigned_user_id')->count()), AnalyticsMath::metric('unassigned', (clone $conversations)->whereNull('assigned_user_id')->count()), AnalyticsMath::metric('high_priority', (clone $conversations)->where('priority', 'high')->count()), AnalyticsMath::metric('urgent', (clone $conversations)->where('priority', 'urgent')->count()), AnalyticsMath::metric('inbound_messages', (int) ($directions['inbound'] ?? 0)), AnalyticsMath::metric('outbound_agent_messages', (int) ($directions['outbound'] ?? 0))],
            'by_session' => (clone $conversations)->join('whatsapp_sessions', 'whatsapp_sessions.id', '=', 'whatsapp_conversations.whatsapp_session_id')->select('whatsapp_sessions.uuid', 'whatsapp_sessions.name', DB::raw('COUNT(*) value'))->groupBy('whatsapp_sessions.id', 'whatsapp_sessions.uuid', 'whatsapp_sessions.name')->orderByDesc('value')->limit(AnalyticsMath::limit($limit))->get(),
            'by_assignee' => (clone $conversations)->leftJoin('users', 'users.id', '=', 'whatsapp_conversations.assigned_user_id')->select('users.uuid', 'users.name', DB::raw('COUNT(*) value'))->groupBy('users.id', 'users.uuid', 'users.name')->orderByDesc('value')->limit(AnalyticsMath::limit($limit))->get(),
            'agent_replies_by_user' => $userCounts('whatsapp_conversation_activities', 'actor_id', 'reply_queued'),
            'notes_by_user' => $userCounts('whatsapp_conversation_notes', 'author_id'),
        ];
    }
}
