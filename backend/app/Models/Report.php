<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Report extends Model
{
    use HasUuids;

    protected $fillable = [
        'reporter_id', 'reportable_type', 'reportable_id', 'reason', 'details',
        'status', 'reviewed_by', 'reviewed_at', 'resolution',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }
}
