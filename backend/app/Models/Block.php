<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    use HasUuids;

    protected $fillable = ['blocker_id', 'blocked_id'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    public function scopeBetween(Builder $query, string $first, string $second): Builder
    {
        return $query->where(function (Builder $nested) use ($first, $second): void {
            $nested->where(['blocker_id' => $first, 'blocked_id' => $second])
                ->orWhere(fn (Builder $reverse) => $reverse->where(['blocker_id' => $second, 'blocked_id' => $first]));
        });
    }
}
