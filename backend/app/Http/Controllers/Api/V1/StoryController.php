<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryResource;
use App\Models\Friendship;
use App\Models\Story;
use App\Support\UserPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        return StoryResource::collection(
            Story::query()->where('expires_at', '>', now())
                ->where(fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->orWhere('visibility', 'public')
                    ->orWhere(fn ($friends) => $friends
                        ->where('visibility', 'friends')
                        ->whereExists(fn ($friendship) => $friendship
                            ->selectRaw('1')
                            ->from('friendships')
                            ->where(fn ($pair) => $pair
                                ->where(fn ($forward) => $forward
                                    ->where('friendships.user_low_id', $user->id)
                                    ->whereColumn('friendships.user_high_id', 'stories.user_id'))
                                ->orWhere(fn ($reverse) => $reverse
                                    ->where('friendships.user_high_id', $user->id)
                                    ->whereColumn('friendships.user_low_id', 'stories.user_id'))))))
                ->with('user')
                ->withExists(['views as viewed_by_me' => fn ($views) => $views->where('user_id', $user->id)])
                ->withCount('views')->oldest()->get()
        );
    }

    public function store(Request $request): StoryResource
    {
        $validated = $request->validate([
            'media' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm', 'max:51200'],
            'caption' => ['nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', Rule::in(['friends', 'public'])],
        ]);
        $disk = config('media.disk');
        $media = $request->file('media');
        $mediaType = str_starts_with((string) $media->getMimeType(), 'video/') ? 'video' : 'image';
        $story = Story::create([
            'user_id' => $request->user()->id,
            'media_disk' => $disk,
            'media_path' => $media->store("stories/{$request->user()->id}", $disk),
            'media_type' => $mediaType,
            'caption' => $validated['caption'] ?? null,
            'visibility' => $validated['visibility'] ?? 'friends',
            'expires_at' => now()->addDay(),
        ]);

        return new StoryResource($story->load('user'));
    }

    public function view(Story $story, Request $request): JsonResponse
    {
        abort_if($story->expires_at->isPast(), 404);
        abort_unless($this->canView($story, $request), 404);
        $story->views()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['viewed_at' => now()]
        );

        return response()->json(['message' => 'Story viewed.']);
    }

    public function destroy(Story $story, Request $request): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);
        Storage::disk($story->media_disk)->delete($story->media_path);
        $story->delete();

        return response()->json(['message' => 'Story deleted.']);
    }

    public function viewers(Story $story, Request $request): JsonResponse
    {
        abort_unless($story->user_id === $request->user()->id, 403);

        return response()->json([
            'data' => $story->views()->with('user')->latest('viewed_at')->get()->map(fn ($view) => [
                'user' => [
                    'id' => $view->user->id,
                    'display_name' => $view->user->display_name,
                    'avatar_url' => $view->user->avatar_path
                        ? Storage::disk(config('media.disk'))->url($view->user->avatar_path)
                        : null,
                ],
                'viewed_at' => $view->viewed_at->toISOString(),
            ]),
        ]);
    }

    private function canView(Story $story, Request $request): bool
    {
        if ($story->user_id === $request->user()->id || $story->visibility === 'public') {
            return true;
        }

        return Friendship::query()->where('pair_key', UserPair::key($story->user_id, $request->user()->id))->exists();
    }
}
