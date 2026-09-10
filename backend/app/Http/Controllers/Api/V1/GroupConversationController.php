<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ChatException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Support\ConversationAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GroupConversationController extends Controller
{
    public function update(Conversation $conversation, Request $request): ConversationResource
    {
        $this->requireManager($conversation, $request);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'avatar' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ]);
        $conversation->fill(collect($validated)->only(['name', 'description'])->all());
        if ($request->hasFile('avatar')) {
            if ($conversation->avatar_path) {
                Storage::disk(config('media.disk'))->delete($conversation->avatar_path);
            }
            $conversation->avatar_path = $request->file('avatar')->store(
                "conversation-avatars/{$conversation->id}",
                config('media.disk'),
            );
        }
        $conversation->save();

        return new ConversationResource($conversation->load('members.user'));
    }

    public function addMember(Conversation $conversation, Request $request): ConversationResource
    {
        $this->requireManager($conversation, $request);
        $validated = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);
        if ($conversation->members()->where('status', 'active')->count() >= 100) {
            throw new ChatException('The group member limit has been reached.', 'GROUP_MEMBER_LIMIT', 422);
        }
        if (Block::query()->between($request->user()->id, $validated['user_id'])->exists()) {
            throw new ChatException('A blocked user cannot be added.', 'MESSAGING_BLOCKED', 403);
        }
        $conversation->members()->updateOrCreate(
            ['user_id' => $validated['user_id']],
            ['role' => 'member', 'status' => 'active', 'joined_at' => now()],
        );

        return new ConversationResource($conversation->load('members.user'));
    }

    public function role(Conversation $conversation, ConversationMember $member, Request $request): ConversationResource
    {
        $this->requireOwner($conversation, $request);
        $this->requireMember($conversation, $member);
        $validated = $request->validate(['role' => ['required', Rule::in(['admin', 'member'])]]);
        if ($member->role === 'owner') {
            throw new ChatException('Transfer ownership before changing the owner role.', 'GROUP_OWNER_PROTECTED', 422);
        }
        $member->update(['role' => $validated['role']]);

        return new ConversationResource($conversation->load('members.user'));
    }

    public function remove(Conversation $conversation, ConversationMember $member, Request $request): JsonResponse
    {
        $this->requireManager($conversation, $request);
        $this->requireMember($conversation, $member);
        if ($member->role === 'owner') {
            throw new ChatException('The owner cannot be removed.', 'GROUP_OWNER_PROTECTED', 422);
        }
        if ($member->role === 'admin' && $this->currentRole($conversation, $request) !== 'owner') {
            throw new ChatException('Only the owner can remove an admin.', 'GROUP_PERMISSION_DENIED', 403);
        }
        $member->update(['status' => 'removed']);

        return response()->json(['message' => 'Group member removed.']);
    }

    public function transfer(Conversation $conversation, ConversationMember $member, Request $request): ConversationResource
    {
        $this->requireOwner($conversation, $request);
        $this->requireMember($conversation, $member);
        DB::transaction(function () use ($conversation, $member, $request): void {
            $conversation->members()->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail()
                ->update(['role' => 'admin']);
            $member->refresh()->update(['role' => 'owner']);
            $conversation->update(['created_by' => $member->user_id]);
        });

        return new ConversationResource($conversation->load('members.user'));
    }

    public function leave(Conversation $conversation, Request $request): JsonResponse
    {
        $this->requireGroup($conversation);
        $membership = $conversation->members()->where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        if ($membership->role === 'owner') {
            throw new ChatException('Transfer ownership before leaving.', 'GROUP_OWNER_PROTECTED', 422);
        }
        $membership->update(['status' => 'left']);

        return response()->json(['message' => 'You left the group.']);
    }

    private function requireManager(Conversation $conversation, Request $request): void
    {
        $this->requireGroup($conversation);
        ConversationAuthorizer::authorize($conversation, $request->user());
        if (! ConversationAuthorizer::canManage($conversation, $request->user())) {
            throw new ChatException('Group management permission is required.', 'GROUP_PERMISSION_DENIED', 403);
        }
    }

    private function requireOwner(Conversation $conversation, Request $request): void
    {
        $this->requireGroup($conversation);
        if ($this->currentRole($conversation, $request) !== 'owner') {
            throw new ChatException('Group owner permission is required.', 'GROUP_PERMISSION_DENIED', 403);
        }
    }

    private function currentRole(Conversation $conversation, Request $request): ?string
    {
        return $conversation->members()->where('user_id', $request->user()->id)->where('status', 'active')->value('role');
    }

    private function requireGroup(Conversation $conversation): void
    {
        if ($conversation->type !== 'group') {
            throw new ChatException('This operation is only available for groups.', 'GROUP_REQUIRED', 422);
        }
    }

    private function requireMember(Conversation $conversation, ConversationMember $member): void
    {
        if ($member->conversation_id !== $conversation->id || $member->status !== 'active') {
            throw new ChatException('Group member was not found.', 'GROUP_MEMBER_NOT_FOUND', 404);
        }
    }
}
