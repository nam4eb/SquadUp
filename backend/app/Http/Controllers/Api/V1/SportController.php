<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SportResource;
use App\Models\Sport;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SportResource::collection(
            Sport::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
        );
    }
}
