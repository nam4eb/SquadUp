<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clans\ClanMembership;
use App\Actions\Clans\CreateClan;
use App\Actions\Clans\TransferClanOwnership;
use App\Exceptions\ClanException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ClanMemberResource;
use App\Http\Resources\ClanResource;
use App\Models\Clan;
use App\Models\ClanMember;
use App\Models\User;
use App\Support\ClanAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:80'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $memberClanIds = ClanMember::query()->where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'invited', 'requested'])->pluck('clan_id');
        $clans = Clan::query()->with([
            'owner', 'members' => fn ($query) => $query->where('user_id', $request->user()->id),
        ])->withCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')])
            ->where('status', 'active')
            ->where(fn ($query) => $query->where('visibility', 'public')->orWhereIn('id', $memberClanIds))
            ->when($validated['query'] ?? null, fn ($query, $term) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$term}%")->orWhere('slug', 'like', "%{$term}%")))
            ->orderBy('name')->paginate($validated['per_page'] ?? 20);

        return ClanResource::collection($clans);
    }

    public function store(Request $request, CreateClan $action): ClanResource
    {
        Gate::authorize('create', Clan::class);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'slug' => ['required', 'string', 'min:3', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:clans,slug'],
            'description' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['sometimes', Rule::in(['public', 'private'])],
            'join_policy' => ['sometimes', Rule::in(['open', 'approval', 'invite_only'])],
        ]);

        return new ClanResource($action->handle($request->user(), $validated));
    }

    public function show(Clan $clan, Request $request): ClanResource
    {
        Gate::authorize('view', $clan);
        $clan->load(['owner', 'members' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->loadCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')]);

        return new ClanResource($clan);
    }

    public function update(Clan $clan, Request $request): ClanResource
    {
        Gate::authorize('view', $clan);
        if (! ClanAuthorizer::allows($clan, $request->user(), 'manage_clan')) {
            throw new ClanException('You cannot manage this clan.', 'CLAN_PERMISSION_DENIED', 403);
        }
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'visibility' => ['sometimes', Rule::in(['public', 'private'])],
            'join_policy' => ['sometimes', Rule::in(['open', 'approval', 'invite_only'])],
            'avatar' => ['sometimes', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'cover' => ['sometimes', 'image', 'mimes:jpeg,png,webp', 'max:10240'],
        ]);
        unset($validated['avatar'], $validated['cover']);
        foreach (['avatar', 'cover'] as $kind) {
            if (! $request->hasFile($kind)) {
                continue;
            }
            $column = "{$kind}_path";
            $oldPath = $clan->{$column};
            $validated[$column] = $request->file($kind)->store("clans/{$clan->id}", 'public');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
            }
        }
        $clan->update($validated);
        $clan->load(['owner', 'members' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->loadCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')]);

        return new ClanResource($clan);
    }

    public function members(Clan $clan, Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view', $clan);
        $statuses = ClanAuthorizer::allows($clan, $request->user(), 'manage_members')
            ? ['active', 'requested', 'invited'] : ['active'];

        return ClanMemberResource::collection(
            $clan->members()->with(['user', 'role'])->whereIn('status', $statuses)
                ->orderByRaw('case when user_id = ? then 0 else 1 end', [$clan->owner_id])
                ->orderBy('joined_at')->paginate(50),
        );
    }

    public function join(Clan $clan, Request $request, ClanMembership $membership): ClanMemberResource
    {
        Gate::authorize('view', $clan);

        return new ClanMemberResource($membership->join($clan, $request->user()));
    }

    public function leave(Clan $clan, Request $request, ClanMembership $membership): JsonResponse
    {
        Gate::authorize('view', $clan);
        $membership->leave($clan, $request->user());

        return response()->json(['message' => 'You left the clan.']);
    }

    public function invite(Clan $clan, Request $request, ClanMembership $membership): ClanMemberResource
    {
        Gate::authorize('view', $clan);
        $validated = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);

        return new ClanMemberResource($membership->invite($clan, $request->user(), User::findOrFail($validated['user_id'])));
    }

    public function review(Clan $clan, ClanMember $member, Request $request, ClanMembership $membership): ClanMemberResource
    {
        Gate::authorize('view', $clan);
        $validated = $request->validate(['accept' => ['required', 'boolean']]);

        return new ClanMemberResource($membership->review($clan, $member, $request->user(), $validated['accept']));
    }

    public function remove(Clan $clan, ClanMember $member, Request $request, ClanMembership $membership): ClanMemberResource
    {
        Gate::authorize('view', $clan);
        $validated = $request->validate(['ban' => ['sometimes', 'boolean']]);

        return new ClanMemberResource($membership->remove($clan, $member, $request->user(), (bool) ($validated['ban'] ?? false)));
    }

    public function transfer(Clan $clan, Request $request, TransferClanOwnership $action): ClanResource
    {
        Gate::authorize('view', $clan);
        $validated = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);

        return new ClanResource(
            $action->handle($clan, $request->user(), User::findOrFail($validated['user_id'])),
        );
    }
}
