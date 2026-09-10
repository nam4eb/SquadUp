<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ClanException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Clan;
use App\Models\ClanAnnouncement;
use App\Support\ClanAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClanAnnouncementController extends Controller
{
    public function index(Clan $clan, Request $request): JsonResponse
    {
        Gate::authorize('view', $clan);
        $this->requireMember($clan, $request);
        $items = $clan->announcements()->with('author')->orderByDesc('is_pinned')
            ->orderByDesc('published_at')->cursorPaginate(30);
        $items->through(fn (ClanAnnouncement $item) => $this->data($item, $request));

        return response()->json($items);
    }

    public function store(Clan $clan, Request $request): JsonResponse
    {
        Gate::authorize('view', $clan);
        $this->requireContentManager($clan, $request);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:10000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);
        $item = $clan->announcements()->create([
            ...$validated, 'author_id' => $request->user()->id, 'published_at' => now(),
        ])->load('author');

        return response()->json(['data' => $this->data($item, $request)], 201);
    }

    public function update(Clan $clan, ClanAnnouncement $announcement, Request $request): JsonResponse
    {
        abort_unless($announcement->clan_id === $clan->id, 404);
        Gate::authorize('view', $clan);
        $this->requireContentManager($clan, $request);
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:160'],
            'body' => ['sometimes', 'string', 'max:10000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);
        $announcement->update($validated);

        return response()->json(['data' => $this->data($announcement->load('author'), $request)]);
    }

    public function destroy(Clan $clan, ClanAnnouncement $announcement, Request $request): JsonResponse
    {
        abort_unless($announcement->clan_id === $clan->id, 404);
        Gate::authorize('view', $clan);
        $this->requireContentManager($clan, $request);
        $announcement->delete();

        return response()->json(['message' => 'Announcement deleted.']);
    }

    private function data(ClanAnnouncement $item, Request $request): array
    {
        return [
            'id' => $item->id, 'title' => $item->title, 'body' => $item->body,
            'is_pinned' => $item->is_pinned,
            'author' => (new UserResource($item->author))->resolve($request),
            'published_at' => $item->published_at->toISOString(),
            'can_manage' => ClanAuthorizer::allows($item->clan()->firstOrFail(), $request->user(), 'manage_content'),
        ];
    }

    private function requireMember(Clan $clan, Request $request): void
    {
        if (ClanAuthorizer::membership($clan, $request->user())?->status?->value !== 'active') {
            throw new ClanException('Active clan membership is required.', 'CLAN_MEMBERSHIP_REQUIRED', 403);
        }
    }

    private function requireContentManager(Clan $clan, Request $request): void
    {
        if (! ClanAuthorizer::allows($clan, $request->user(), 'manage_content')) {
            throw new ClanException('You cannot manage clan content.', 'CLAN_PERMISSION_DENIED', 403);
        }
    }
}
