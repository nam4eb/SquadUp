<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $fillable = [
        'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size',
        'width', 'height', 'duration_ms',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute(
            'media.show',
            now()->addMinutes((int) config('media.url_ttl_minutes')),
            ['media' => $this->id]
        );
    }
}
