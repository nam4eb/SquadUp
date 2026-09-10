<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ChatException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\ConversationAuthorizer;
use App\Support\UserPair;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->where(function ($query) use ($user): void {
                $query->whereHas('members', fn ($members) => $members->where('user_id', $user->id)->where('status', 'active'))
                    ->orWhereHas('clan.members', fn ($members) => $members->where('user_id', $user->id)->where('status', 'active'))
                    ->orWhereHas('activity.participants', fn ($participants) => $participants->where('user_id', $user->id)->where('status', 'joined'));
            })
            ->with([
                'members' => fn ($members) => $members->where(fn ($scope) => $scope
                    ->where('user_id', $user->id)
                    ->orWhereIn('conversation_id', Conversation::query()->select('id')->where('type', 'direct'))),
                'members.user', 'activity', 'latestMessage.sender',
            ])
            ->addSelect([
                'unread_count' => Message::query()->selectRaw('COUNT(*)')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->where('messages.sender_id', '!=', $user->id)
                    ->whereNull('messages.deleted_at')
                    ->whereRaw("messages.created_at > COALESCE((SELECT last_read_at FROM conversation_members WHERE conversation_id = conversations.id AND user_id = ? LIMIT 1), '1970-01-01')", [$user->id]),
            ])
            ->orderByDesc('last_message_at')->orderByDesc('updated_at')->paginate(30);

        return ConversationResource::collection($conversations);
    }

    public function show(Conversation $conversation, Request $request): ConversationResource
    {
        ConversationAuthorizer::authorize($conversation, $request->user());

        return new ConversationResource($conversation->load(['members.user', 'activity', 'latestMessage.sender', 'latestMessage.reactions', 'latestMessage.reads']));
    }

    public function direct(Request $request): ConversationResource
    {
        $validated = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);
        $user = $request->user();
        $other = User::findOrFail($validated['user_id']);
        if ($user->is($other)) {
            throw new ChatException('You cannot message yourself.', 'CANNOT_MESSAGE_SELF', 422);
        }
        if (Block::query()->between($user->id, $other->id)->exists()) {
            throw new ChatException('Messaging is unavailable.', 'MESSAGING_BLOCKED', 403);
        }
        $key = UserPair::key($user->id, $other->id);
        $conversation = DB::transaction(function () use ($key, $user, $other): Conversation {
            $conversation = Conversation::query()->firstOrCreate(
                ['direct_key' => $key],
                ['type' => 'direct', 'created_by' => $user->id],
            );
            foreach ([$user, $other] as $member) {
                $conversation->members()->firstOrCreate(
                    ['user_id' => $member->id],
                    ['role' => 'member', 'status' => 'active', 'joined_at' => now()],
                );
            }

            return $conversation;
        });

        return new ConversationResource($conversation->load('members.user'));
    }

    public function group(Request $request): ConversationResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'member_ids' => ['required', 'array', 'min:1', 'max:99'],
            'member_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
        ]);
        $creator = $request->user();
        $memberIds = collect($validated['member_ids'])->push($creator->id)->unique()->values();
        if (Block::query()->where(function ($query) use ($creator, $memberIds): void {
            $query->where('blocker_id', $creator->id)->whereIn('blocked_id', $memberIds)
                ->orWhere(fn ($reverse) => $reverse->where('blocked_id', $creator->id)->whereIn('blocker_id', $memberIds));
        })->exists()) {
            throw new ChatException('A blocked user cannot be added to the group.', 'MESSAGING_BLOCKED', 403);
        }
        $conversation = DB::transaction(function () use ($validated, $creator, $memberIds): Conversation {
            $conversation = Conversation::create([
                'type' => 'group', 'name' => $validated['name'],
                'description' => $validated['description'] ?? null, 'created_by' => $creator->id,
            ]);
            foreach ($memberIds as $userId) {
                $conversation->members()->create([
                    'user_id' => $userId,
                    'role' => $userId === $creator->id ? 'owner' : 'member',
                    'status' => 'active', 'joined_at' => now(),
                ]);
            }

            return $conversation;
        });

        return new ConversationResource($conversation->load('members.user'));
    }
}
