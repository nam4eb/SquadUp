<?php

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\ActivityVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'host_id', 'category_id', 'topic_id', 'sport_id', 'venue_id', 'title', 'description', 'cover_path',
        'recurrence_parent_id', 'recurrence_index', 'recurrence_rule',
        'starts_at', 'ends_at', 'timezone', 'location_name', 'location_address',
        'latitude', 'longitude', 'skill_min', 'skill_max', 'match_format', 'fee', 'currency',
        'min_participants', 'max_participants', 'status',
        'visibility', 'allow_waitlist', 'allow_friend_invitations', 'require_approval',
        'allow_join_by_link', 'password_hash', 'minimum_age', 'rules',
        'equipment_requirements', 'notes',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'status' => ActivityStatus::class, 'visibility' => ActivityVisibility::class,
            'allow_waitlist' => 'boolean', 'allow_friend_invitations' => 'boolean',
            'require_approval' => 'boolean', 'allow_join_by_link' => 'boolean',
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'rules' => 'array',
            'fee' => 'decimal:2',
            'recurrence_rule' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ActivityCategory::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ActivityTopic::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ActivityInvitation::class);
    }

    public function invitationTokens(): HasMany
    {
        return $this->hasMany(ActivityInvitationToken::class);
    }

    public function clanEvent(): HasOne
    {
        return $this->hasOne(ClanEvent::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'recurrence_parent_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ActivityLike::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ActivityComment::class);
    }
}
