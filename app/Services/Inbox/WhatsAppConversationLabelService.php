<?php

namespace App\Services\Inbox;

use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppConversationLabel;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WhatsAppConversationLabelService
{
    public function __construct(private ConversationActivityRecorder $activity, private AuditService $audit) {}

    public function sync(WhatsAppConversation $c, User $actor, array $labels): WhatsAppConversation
    {
        return DB::transaction(function () use ($c, $actor, $labels) {
            $c = WhatsAppConversation::forTenant($c->tenant_id)->lockForUpdate()->findOrFail($c->id);
            $ids = [];
            foreach ($labels as $item) {
                if (! filled($item['name'] ?? null)) {
                    continue;
                }
                $color = $item['color'] ?? null;
                if ($color && ! preg_match('/^(slate|red|orange|amber|green|blue|indigo|violet|pink|gray)$/', $color)) {
                    throw ValidationException::withMessages(['labels' => 'Select a supported label color.']);
                }$name = trim($item['name']);
                $label = WhatsAppConversationLabel::withTrashed()->firstOrCreate(['tenant_id' => $c->tenant_id, 'slug' => Str::slug($name)], ['name' => $name, 'color' => $color]);
                if ($label->trashed()) {
                    $label->restore();
                }$ids[$label->id] = ['tenant_id' => $c->tenant_id, 'uuid' => (string) Str::uuid(), 'assigned_by' => $actor->id];
            }$before = $c->labels()->pluck('whatsapp_conversation_labels.uuid')->all();
            $c->labels()->sync($ids);
            $c->update(['last_agent_activity_at' => now()]);
            $after = $c->labels()->pluck('whatsapp_conversation_labels.uuid')->all();
            foreach (array_diff($after, $before) as $uuid) {
                $this->activity->record($c, $actor, 'label_added', ['label_uuid' => $uuid]);
            }foreach (array_diff($before, $after) as $uuid) {
                $this->activity->record($c, $actor, 'label_removed', ['label_uuid' => $uuid]);
            }$this->audit->recordDomain('whatsapp_inbox.labels_changed', $actor, $c->tenant, $c, ['conversation_uuid' => $c->uuid, 'label_count' => count($after)]);

            return $c->load('labels');
        }, 3);
    }
}
