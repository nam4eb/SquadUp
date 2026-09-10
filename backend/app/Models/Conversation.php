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
        // latestOfMany() aggregates the UUID primary key with MAX(), which is
        // unsupported by PostgreSQL. Ordering is also correct for eager loads
        // because a has-one relation keeps the first matching child per parent.
        return $this->hasOne(Message::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
