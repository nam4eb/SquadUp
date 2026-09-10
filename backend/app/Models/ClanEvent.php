<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanEvent extends Model
{
    use HasUuids;

    protected $fillable = ['clan_id', 'activity_id', 'created_by'];

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
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
