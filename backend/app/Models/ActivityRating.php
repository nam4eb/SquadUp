<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRating extends Model
{
    use HasUuids;

    protected $fillable = [
        'activity_id', 'sport_id', 'rater_id', 'ratee_id', 'sportsmanship',
        'skill', 'reliability', 'comment',
        'invalidated_at', 'invalidated_by', 'invalidation_reason',
    ];

    protected function casts(): array
    {
        return ['invalidated_at' => 'datetime'];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ratee_id');
    }

    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by');
    }
}
