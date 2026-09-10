<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VenueResource;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VenueController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return VenueResource::collection(Venue::query()
            ->when(trim($validated['query'] ?? ''), fn ($query, $term) => $query->where(
                fn ($search) => $search->where('name', 'like', "%{$term}%")->orWhere('address', 'like', "%{$term}%")
            ))
            ->when($validated['city'] ?? null, fn ($query, $city) => $query->where('city', $city))
            ->orderByDesc('is_verified')->orderBy('name')
            ->paginate($validated['per_page'] ?? 20));
    }
}
