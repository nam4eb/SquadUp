<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReputation extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'ratings_count', 'sportsmanship_average', 'skill_average',
        'reliability_average', 'attended_count', 'absent_count', 'attendance_rate',
    ];

    protected function casts(): array
    {
        return [
            'sportsmanship_average' => 'float', 'skill_average' => 'float',
            'reliability_average' => 'float', 'attendance_rate' => 'float',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
