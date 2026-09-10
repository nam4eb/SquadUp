<?php

namespace App\Models;

use App\Enums\ActivityParticipantStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipant extends Model
{
    use HasUuids;

    protected $fillable = [
        'activity_id', 'user_id', 'role', 'status', 'joined_at', 'left_at',
        'attendance_marked_at', 'attendance_marked_by', 'waitlist_position',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityParticipantStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'attendance_marked_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
