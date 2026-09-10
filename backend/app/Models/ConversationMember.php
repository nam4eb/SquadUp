<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMember extends Model
{
    use HasUuids;

    protected $fillable = ['conversation_id', 'user_id', 'role', 'status', 'joined_at', 'last_read_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'last_read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
