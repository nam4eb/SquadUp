<?php

namespace App\Models;

use App\Enums\ActivityInvitationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityInvitation extends Model
{
    use HasUuids;

    protected $fillable = ['activity_id', 'inviter_id', 'invitee_id', 'status', 'expires_at', 'responded_at'];

    protected function casts(): array
    {
        return [
            'status' => ActivityInvitationStatus::class,
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }
}
