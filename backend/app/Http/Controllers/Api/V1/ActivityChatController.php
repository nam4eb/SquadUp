<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Activity;
use App\Models\Conversation;
use App\Support\ConversationAuthorizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityChatController extends Controller
{
    public function store(Activity $activity, Request $request): ConversationResource
    {
        Gate::authorize('manage', $activity);
        $conversation = Conversation::firstOrCreate(
            ['activity_id' => $activity->id],
            [
                'type' => 'event', 'name' => $activity->title,
                'description' => 'Activity conversation', 'created_by' => $request->user()->id,
            ],
        );

        return new ConversationResource($conversation->load('activity'));
    }

    public function show(Activity $activity, Request $request): ConversationResource
    {
        $conversation = $activity->conversation()->firstOrFail();
        ConversationAuthorizer::authorize($conversation, $request->user());

        return new ConversationResource($conversation->load('activity'));
    }
}
