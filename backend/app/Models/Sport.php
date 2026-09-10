<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug', 'name', 'icon', 'min_players', 'max_players', 'supports_team',
        'supports_singles', 'supports_doubles', 'skill_system', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supports_team' => 'boolean',
            'supports_singles' => 'boolean',
            'supports_doubles' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function userProfiles(): HasMany
    {
        return $this->hasMany(UserSportProfile::class);
    }
}
