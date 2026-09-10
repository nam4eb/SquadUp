<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clan extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'owner_id', 'name', 'slug', 'description', 'avatar_path', 'cover_path',
        'visibility', 'join_policy', 'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(ClanRole::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(ClanMember::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClanEvent::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(ClanAnnouncement::class);
    }
}
