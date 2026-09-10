<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClanAnnouncement extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['clan_id', 'author_id', 'title', 'body', 'is_pinned', 'published_at'];

    protected function casts(): array
    {
        return ['is_pinned' => 'boolean', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }
}
