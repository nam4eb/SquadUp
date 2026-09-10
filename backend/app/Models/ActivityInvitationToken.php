<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityInvitationToken extends Model
{
    use HasUuids;

    protected $fillable = [
        'activity_id', 'created_by', 'type', 'secret_hash', 'max_uses',
        'uses_count', 'expires_at', 'revoked_at',
    ];

    protected $hidden = ['secret_hash'];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer', 'uses_count' => 'integer',
            'expires_at' => 'datetime', 'revoked_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
