<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationSignal;
use App\Events\MessageChanged;
use App\Exceptions\ChatException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Activity;
use App\Models\Clan;
use App\Models\Conversation;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\MessageRead;
use App\Models\Story;
use App\Notifications\ChatMessageReceived;
use App\Notifications\MentionReceived;
use App\Services\MessageMediaService;
use App\Services\NotificationPreferenceService;
use App\Support\ConversationAuthorizer;
use App\Support\UserPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function index(Conversation $conversation, Request $request): AnonymousResourceCollection
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return MessageResource::collection(
            $conversation->messages()->withTrashed()->with(['sender', 'reactions', 'media', 'mentions.user'])
                ->with('story')
                ->withCount('reads')
                ->orderByDesc('created_at')->orderByDesc('id')
                ->cursorPaginate($validated['limit'] ?? 30),
        );
    }

    public function store(
        Conversation $conversation,
        Request $request,
        MessageMediaService $mediaService,
        NotificationPreferenceService $preferences,
    ): MessageResource {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $validated = $request->validate([
            'client_message_id' => ['sometimes', 'uuid'],
            'type' => ['sometimes', Rule::in(['text', 'image', 'video', 'file', 'audio', 'location', 'event', 'clan', 'story_reply'])],
            'body' => ['nullable', 'required_without_all:file,payload', 'string', 'max:4000'],
            'payload' => ['nullable', 'array'],
            'payload.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'payload.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'payload.label' => ['nullable', 'string', 'max:240'],
            'payload.activity_id' => ['nullable', 'uuid'],
            'payload.clan_id' => ['nullable', 'uuid'],
            'file' => [
                'nullable', 'file', 'max:'.config('media.max_kilobytes'),
                'mimetypes:'.implode(',', config('media.allowed_mime_types')),
            ],
            'mention_ids' => ['sometimes', 'array', 'max:50'],
            'mention_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
            'reply_to_id' => ['nullable', 'uuid', 'exists:messages,id'],
            'story_id' => ['nullable', 'uuid', 'exists:stories,id'],
        ]);
        $this->validatePayload($request, $validated);
        $this->validateReply($conversation, $validated['reply_to_id'] ?? null);
        $mentionedUsers = $this->resolveMentions($conversation, $request, $validated);
        $file = $request->file('file');
        $created = false;
        $message = DB::transaction(function () use ($conversation, $request, $validated, $mentionedUsers, $file, $mediaService, &$created): Message {
            $message = $conversation->messages()->firstOrCreate([
                'sender_id' => $request->user()->id,
                'client_message_id' => $validated['client_message_id'] ?? (string) Str::uuid(),
            ], [
                'sender_id' => $request->user()->id,
                'type' => $file ? $mediaService->messageType($file) : ($validated['type'] ?? 'text'),
                'body' => isset($validated['body']) ? trim($validated['body']) : null,
                'payload' => $validated['payload'] ?? null,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
                'story_id' => $validated['story_id'] ?? null,
            ]);
            $created = $message->wasRecentlyCreated;
            if ($file && $created) {
                $mediaService->attach($message, $request->user(), $file);
            }
            if ($created) {
                foreach ($mentionedUsers as $user) {
                    $message->mentions()->create(['user_id' => $user->id]);
                }
                $conversation->update(['last_message_at' => $message->created_at]);
            }

            return $message;
        });
        if ($created) {
            broadcast(new MessageChanged($message, 'created'))->toOthers();
            $this->notifyRecipients($conversation, $message->load('sender'), $mentionedUsers, $preferences);
        }

        return new MessageResource($message->load(['sender', 'reactions', 'reads', 'media', 'mentions.user', 'story']));
    }

    public function update(Conversation $conversation, Message $message, Request $request): MessageResource
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $this->requireMessage($conversation, $message);
        if ($message->sender_id !== $request->user()->id || $message->trashed()) {
            throw new ChatException('You cannot edit this message.', 'MESSAGE_EDIT_FORBIDDEN', 403);
        }
        $validated = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => trim($validated['body']), 'edited_at' => now()]);
        broadcast(new MessageChanged($message, 'updated'))->toOthers();

        return new MessageResource($message->load(['sender', 'reactions', 'reads', 'media', 'mentions.user']));
    }

    public function destroy(Conversation $conversation, Message $message, Request $request): JsonResponse
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $this->requireMessage($conversation, $message);
        if ($message->sender_id !== $request->user()->id && ! ConversationAuthorizer::canManage($conversation, $request->user())) {
            throw new ChatException('You cannot delete this message.', 'MESSAGE_DELETE_FORBIDDEN', 403);
        }
        $message->delete();
        broadcast(new MessageChanged($message, 'deleted'))->toOthers();

        return response()->json(['message' => 'Message deleted.']);
    }

    public function react(Conversation $conversation, Message $message, Request $request): JsonResponse
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $this->requireMessage($conversation, $message);
        $validated = $request->validate(['reaction' => ['required', 'string', 'max:32']]);
        MessageReaction::firstOrCreate([
            'message_id' => $message->id, 'user_id' => $request->user()->id,
            'reaction' => $validated['reaction'],
        ]);
        broadcast(new ConversationSignal($conversation->id, 'message.reaction', [
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'reaction' => $validated['reaction'],
            'action' => 'added',
        ]))->toOthers();

        return response()->json(['message' => 'Reaction added.']);
    }

    public function unreact(Conversation $conversation, Message $message, Request $request): JsonResponse
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $this->requireMessage($conversation, $message);
        $validated = $request->validate(['reaction' => ['required', 'string', 'max:32']]);
        MessageReaction::where([
            'message_id' => $message->id, 'user_id' => $request->user()->id,
            'reaction' => $validated['reaction'],
        ])->delete();
        broadcast(new ConversationSignal($conversation->id, 'message.reaction', [
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'reaction' => $validated['reaction'],
            'action' => 'removed',
        ]))->toOthers();

        return response()->json(['message' => 'Reaction removed.']);
    }

    public function read(Conversation $conversation, Message $message, Request $request): JsonResponse
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $this->requireMessage($conversation, $message);
        MessageRead::updateOrCreate(
            ['message_id' => $message->id, 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );
        $conversation->members()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['role' => 'member', 'status' => 'active', 'joined_at' => now(), 'last_read_at' => now()],
        );
        broadcast(new ConversationSignal($conversation->id, 'message.read', [
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'read_at' => now()->toISOString(),
        ]))->toOthers();

        return response()->json(['message' => 'Message marked as read.']);
    }

    public function typing(Conversation $conversation, Request $request): JsonResponse
    {
        ConversationAuthorizer::authorize($conversation, $request->user());
        $validated = $request->validate(['typing' => ['required', 'boolean']]);
        broadcast(new ConversationSignal($conversation->id, 'conversation.typing', [
            'user_id' => $request->user()->id,
            'display_name' => $request->user()->display_name,
            'typing' => $validated['typing'],
            'expires_in' => 5,
        ]))->toOthers();

        return response()->json(['message' => 'Typing state broadcast.']);
    }

    private function requireMessage(Conversation $conversation, Message $message): void
    {
        if ($message->conversation_id !== $conversation->id) {
            throw new ChatException('Message is not in this conversation.', 'MESSAGE_CONVERSATION_MISMATCH', 404);
        }
    }

    private function validateReply(Conversation $conversation, ?string $replyId): void
    {
        if ($replyId && ! $conversation->messages()->whereKey($replyId)->exists()) {
            throw new ChatException('Reply target is not in this conversation.', 'MESSAGE_CONVERSATION_MISMATCH', 422);
        }
    }

    private function validatePayload(Request $request, array $validated): void
    {
        $type = $validated['type'] ?? 'text';
        $payload = $validated['payload'] ?? [];
        if ($type === 'location' && (! isset($payload['latitude'], $payload['longitude']))) {
            throw ValidationException::withMessages(['payload' => ['Location messages require latitude and longitude.']]);
        }
        if ($type === 'event') {
            $activity = isset($payload['activity_id']) ? Activity::find($payload['activity_id']) : null;
            if (! $activity) {
                throw ValidationException::withMessages(['payload.activity_id' => ['A valid activity is required.']]);
            }
            Gate::forUser($request->user())->authorize('view', $activity);
        }
        if ($type === 'clan') {
            $clan = isset($payload['clan_id']) ? Clan::find($payload['clan_id']) : null;
            if (! $clan) {
                throw ValidationException::withMessages(['payload.clan_id' => ['A valid clan is required.']]);
            }
            Gate::forUser($request->user())->authorize('view', $clan);
        }
        if ($type === 'story_reply') {
            $story = isset($validated['story_id']) ? Story::find($validated['story_id']) : null;
            $canView = $story && $story->expires_at->isFuture() && (
                $story->user_id === $request->user()->id
                || $story->visibility === 'public'
                || Friendship::query()->where('pair_key', UserPair::key($story->user_id, $request->user()->id))->exists()
            );
            if (! $canView || ! $this->activeUsers($request->route('conversation'))->contains('id', $story->user_id)) {
                throw ValidationException::withMessages(['story_id' => ['The story is unavailable in this conversation.']]);
            }
        }
    }

    private function resolveMentions(Conversation $conversation, Request $request, array $validated)
    {
        preg_match_all('/@([A-Za-z0-9_.-]{3,40})/', $validated['body'] ?? '', $matches);
        $members = $this->activeUsers($conversation);
        $requestedIds = collect($validated['mention_ids'] ?? []);
        $validIds = $members->pluck('id');
        if ($requestedIds->diff($validIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mention_ids' => ['Every mentioned user must be an active conversation member.'],
            ]);
        }

        return $members
            ->filter(fn ($user) => $requestedIds->contains($user->id) || in_array($user->username, $matches[1] ?? [], true))
            ->reject(fn ($user) => $user->id === $request->user()->id)
            ->unique('id')->values();
    }

    private function notifyRecipients(
        Conversation $conversation,
        Message $message,
        $mentionedUsers,
        NotificationPreferenceService $preferences,
    ): void {
        $mentionedIds = $mentionedUsers->pluck('id');
        foreach ($mentionedUsers as $user) {
            if ($this->notificationEnabled($preferences, $user, 'mention')) {
                $user->notify(new MentionReceived($message));
            }
        }

        $recipients = $this->activeUsers($conversation)
            ->reject(fn ($user) => $user->id === $message->sender_id || $mentionedIds->contains($user->id));
        Notification::send(
            $recipients->filter(fn ($user) => $this->notificationEnabled($preferences, $user, 'chat_message')),
            new ChatMessageReceived($message),
        );
    }

    private function activeUsers(Conversation $conversation)
    {
        if ($conversation->type === 'clan') {
            return $conversation->clan->members()->where('status', 'active')
                ->with('user')->get()->pluck('user')->filter();
        }
        if ($conversation->type === 'event') {
            return $conversation->activity->participants()->where('status', 'joined')
                ->with('user')->get()->pluck('user')->filter();
        }

        return $conversation->members()->where('status', 'active')
            ->with('user')->get()->pluck('user')->filter();
    }

    private function notificationEnabled(NotificationPreferenceService $preferences, $user, string $type): bool
    {
        return $preferences->enabled($user, $type, 'in_app')
            || $preferences->enabled($user, $type, 'push');
    }
}
