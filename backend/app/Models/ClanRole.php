<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClanRole extends Model
{
    use HasUuids;

    protected $fillable = ['clan_id', 'name', 'slug', 'color', 'sort_order', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(ClanRolePermission::class);
    }
}
