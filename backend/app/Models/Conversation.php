<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasUuids;

    protected $fillable = ['type', 'clan_id', 'activity_id', 'direct_key', 'name', 'description', 'avatar_path', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function latestMessage(): HasOne
    {
        // ofMany also aggregates the UUID primary key as a tie-breaker, which
        // PostgreSQL does not support. Select one non-deleted ID per chat.
        return $this->hasOne(Message::class)->where('messages.id', '=', function ($query): void {
            $query->select('latest.id')->from('messages as latest')
                ->whereColumn('latest.conversation_id', 'messages.conversation_id')
                ->whereNull('latest.deleted_at')
                ->orderByDesc('latest.created_at')->orderByDesc('latest.id')
                ->limit(1);
        });
    }
}
