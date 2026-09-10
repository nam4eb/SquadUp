<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    use HasUuids;

    protected $fillable = ['message_id', 'user_id', 'reaction'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
