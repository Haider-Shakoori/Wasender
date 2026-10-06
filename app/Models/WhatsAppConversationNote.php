<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class WhatsAppConversationNote extends Model
{
    use HasUuid, SoftDeletes;

    protected $table = 'whatsapp_conversation_notes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function mentions(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'whatsapp_conversation_note_mentions', 'note_id', 'mentioned_user_id')->withPivot('tenant_id');
    }
}
