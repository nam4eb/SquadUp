<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HiddenActivity extends Model
{
    use HasUuids;

    protected $fillable = ['activity_id', 'user_id'];
}
