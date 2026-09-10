<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MessageChanged;
use App\Exceptions\ClanException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Models\Clan;
use App\Models\Message;
use App\Notifications\ChatMessageReceived;
use App\Notifications\MentionReceived;
use App\Services\MessageMediaService;
use App\Services\NotificationPreferenceService;
use App\Support\ClanAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ClanMessageController extends Controller
{
    public function index(Clan $clan, Request $request): AnonymousResourceCollection
    {
        Gate::authorize('view', $clan);
        $this->requireActiveMember($clan, $request);
        $validated = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $conversation = $clan->conversation()->firstOrFail();

        return MessageResource::collection(
            $conversation->messages()->withTrashed()->with(['sender', 'media', 'mentions.user', 'reactions', 'reads'])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->cursorPaginate($validated['limit'] ?? 30),
        );
    }

    public function store(
        Clan $clan,
        Request $request,
        MessageMediaService $mediaService,
        NotificationPreferenceService $preferences,
    ): MessageResource {
        Gate::authorize('view', $clan);
        $this->requireActiveMember($clan, $request);
        $validated = $request->validate([
            'body' => ['nullable', 'required_without:file', 'string', 'max:4000'],
            'file' => [
                'nullable', 'file', 'max:'.config('media.max_kilobytes'),
                'mimetypes:'.implode(',', config('media.allowed_mime_types')),
            ],
            'mention_ids' => ['sometimes', 'array', 'max:50'],
            'mention_ids.*' => ['uuid', 'distinct', 'exists:users,id'],
            'reply_to_id' => ['nullable', 'uuid', 'exists:messages,id'],
        ]);
        $conversation = $clan->conversation()->firstOrFail();
        if (isset($validated['reply_to_id']) && ! $conversation->messages()->whereKey($validated['reply_to_id'])->exists()) {
            throw new ClanException('Reply target is not in this clan conversation.', 'MESSAGE_CONVERSATION_MISMATCH', 422);
        }
        preg_match_all('/@([A-Za-z0-9_.-]{3,40})/', $validated['body'] ?? '', $matches);
        $members = $clan->members()->where('status', 'active')->with('user')->get()->pluck('user')->filter();
        $requestedIds = collect($validated['mention_ids'] ?? []);
        if ($requestedIds->diff($members->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mention_ids' => ['Every mentioned user must be an active clan member.'],
            ]);
        }
        $mentioned = $members->filter(fn ($user) => $requestedIds->contains($user->id)
            || in_array($user->username, $matches[1] ?? [], true))
            ->reject(fn ($user) => $user->id === $request->user()->id)->unique('id')->values();
        $file = $request->file('file');
        $message = DB::transaction(function () use ($conversation, $request, $validated, $file, $mediaService, $mentioned): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'type' => $file ? $mediaService->messageType($file) : 'text',
                'body' => isset($validated['body']) ? trim($validated['body']) : null,
                'reply_to_id' => $validated['reply_to_id'] ?? null,
            ]);
            if ($file) {
                $mediaService->attach($message, $request->user(), $file);
            }
            foreach ($mentioned as $user) {
                $message->mentions()->create(['user_id' => $user->id]);
            }
            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });
        $message->load('sender');
        broadcast(new MessageChanged($message, 'created'))->toOthers();
        foreach ($members->reject(fn ($user) => $user->id === $request->user()->id) as $recipient) {
            $isMentioned = $mentioned->contains('id', $recipient->id);
            $type = $isMentioned ? 'mention' : 'chat_message';
            if ($preferences->enabled($recipient, $type, 'in_app')
                || $preferences->enabled($recipient, $type, 'push')) {
                $recipient->notify($isMentioned ? new MentionReceived($message) : new ChatMessageReceived($message));
            }
        }

        return new MessageResource($message->load(['sender', 'media', 'mentions.user', 'reactions', 'reads']));
    }

    public function destroy(Clan $clan, Message $message, Request $request): JsonResponse
    {
        Gate::authorize('view', $clan);
        $this->requireActiveMember($clan, $request);
        if ($message->conversation_id !== $clan->conversation()->value('id')) {
            throw new ClanException('Message is not in this clan conversation.', 'MESSAGE_CONVERSATION_MISMATCH', 404);
        }
        if ($message->sender_id !== $request->user()->id && ! ClanAuthorizer::allows($clan, $request->user(), 'manage_chat')) {
            throw new ClanException('You cannot delete this message.', 'CLAN_PERMISSION_DENIED', 403);
        }
        $message->delete();
        broadcast(new MessageChanged($message, 'deleted'))->toOthers();

        return response()->json(['message' => 'Message deleted.']);
    }

    private function requireActiveMember(Clan $clan, Request $request): void
    {
        if (ClanAuthorizer::membership($clan, $request->user())?->status?->value !== 'active') {
            throw new ClanException('Active clan membership is required.', 'CLAN_MEMBERSHIP_REQUIRED', 403);
        }
    }
}
