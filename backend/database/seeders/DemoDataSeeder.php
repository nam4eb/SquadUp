<?php

namespace Database\Seeders;

use App\Models\ActivityCategory;
use App\Models\ActivityTopic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Support\UserPair;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed the database with a complete demo flow for login + social + activity + clan + chat.
     */
    public function run(): void
    {
        $now = now();

        $demo = User::updateOrCreate(
            ['email' => 'demo@squadup.test'],
            [
                'username' => 'demo.user',
                'display_name' => 'Demo User',
                'email' => 'demo@squadup.test',
                'email_verified_at' => $now,
                'password' => Hash::make('DemoPass123!'),
                'avatar_path' => null,
                'cover_path' => null,
                'bio' => 'I am testing the full SquadUp flow.',
                'gender' => 'male',
                'date_of_birth' => '1998-05-15',
                'location' => 'Bangkok, Thailand',
                'last_active_at' => $now,
                'presence_status' => 'online',
                'account_status' => 'active',
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $alice = User::updateOrCreate(
            ['email' => 'alice@squadup.test'],
            [
                'username' => 'alice.park',
                'display_name' => 'Alice Park',
                'email' => 'alice@squadup.test',
                'email_verified_at' => $now,
                'password' => Hash::make('DemoPass123!'),
                'bio' => 'Morning workouts and good coffee.',
                'location' => 'Bangkok, Thailand',
                'last_active_at' => $now,
                'presence_status' => 'online',
                'account_status' => 'active',
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $bryan = User::updateOrCreate(
            ['email' => 'bryan@squadup.test'],
            [
                'username' => 'bryan.smith',
                'display_name' => 'Bryan Smith',
                'email' => 'bryan@squadup.test',
                'email_verified_at' => $now,
                'password' => Hash::make('DemoPass123!'),
                'bio' => 'Team captain and weekend runner.',
                'location' => 'Chiang Mai, Thailand',
                'last_active_at' => $now,
                'presence_status' => 'away',
                'account_status' => 'active',
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $mila = User::updateOrCreate(
            ['email' => 'mila@squadup.test'],
            [
                'username' => 'mila.nguyen',
                'display_name' => 'Mila Nguyen',
                'email' => 'mila@squadup.test',
                'email_verified_at' => $now,
                'password' => Hash::make('DemoPass123!'),
                'bio' => 'Cycling enthusiast and foodie.',
                'location' => 'Hanoi, Vietnam',
                'last_active_at' => $now,
                'presence_status' => 'busy',
                'account_status' => 'active',
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $rival = User::updateOrCreate(
            ['email' => 'rival@squadup.test'],
            [
                'username' => 'rival.lee',
                'display_name' => 'Rival Lee',
                'email' => 'rival@squadup.test',
                'email_verified_at' => $now,
                'password' => Hash::make('DemoPass123!'),
                'bio' => 'Testing blocked users and edge cases.',
                'location' => 'Singapore',
                'last_active_at' => $now,
                'presence_status' => 'offline',
                'account_status' => 'active',
                'remember_token' => Str::random(10),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $demo->createToken('demo-device');

        DB::table('social_accounts')->updateOrInsert(
            ['user_id' => $demo->id, 'provider' => 'google'],
            [
                'id' => (string) Str::uuid(),
                'provider_subject' => 'google-demo-user',
                'provider_email' => 'demo@squadup.test',
                'metadata' => json_encode(['avatar_url' => null]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach (['friend_request', 'activity_reminder', 'chat_message', 'clan_update'] as $type) {
            DB::table('notification_preferences')->updateOrInsert(
                ['user_id' => $demo->id, 'type' => $type],
                [
                    'id' => (string) Str::uuid(),
                    'in_app_enabled' => true,
                    'push_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        foreach ([
            ['user' => $demo, 'device' => 'Pixel 8', 'platform' => 'android', 'token' => 'android-demo-token-001'],
            ['user' => $alice, 'device' => 'iPhone 15', 'platform' => 'ios', 'token' => 'ios-demo-token-001'],
        ] as $deviceSeed) {
            DB::table('push_devices')->updateOrInsert(
                ['token' => $deviceSeed['token']],
                [
                    'id' => (string) Str::uuid(),
                    'user_id' => $deviceSeed['user']->id,
                    'platform' => $deviceSeed['platform'],
                    'device_name' => $deviceSeed['device'],
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $pairKey = $this->pairKey($demo->id, $alice->id);
        DB::table('friendships')->updateOrInsert(
            ['pair_key' => $pairKey],
            [
                'id' => (string) Str::uuid(),
                'user_low_id' => min($demo->id, $alice->id),
                'user_high_id' => max($demo->id, $alice->id),
                'accepted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('friend_requests')->updateOrInsert(
            ['pair_key' => $this->pairKey($demo->id, $bryan->id)],
            [
                'id' => (string) Str::uuid(),
                'sender_id' => $bryan->id,
                'receiver_id' => $demo->id,
                'status' => 'pending',
                'responded_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('blocks')->updateOrInsert(
            ['blocker_id' => $demo->id, 'blocked_id' => $rival->id],
            [
                'id' => (string) Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $category = ActivityCategory::query()->firstOrFail();
        $topic = ActivityTopic::query()->firstOrFail();

        $activityId = DB::table('activities')
            ->where('host_id', $demo->id)
            ->where('title', 'Sunrise Run Club')
            ->value('id') ?? (string) Str::uuid();
        DB::table('activities')->updateOrInsert(
            ['id' => $activityId],
            [
                'host_id' => $demo->id,
                'category_id' => $category->id,
                'topic_id' => $topic->id,
                'title' => 'Sunrise Run Club',
                'description' => 'A social trail run with warm-up and coffee stop afterward.',
                'cover_path' => null,
                'starts_at' => $now->copy()->addDays(2),
                'ends_at' => $now->copy()->addDays(2)->addHours(2),
                'timezone' => 'Asia/Bangkok',
                'location_name' => 'Benjakitti Park',
                'location_address' => 'Chatuchak District, Bangkok',
                'latitude' => 13.728,
                'longitude' => 100.562,
                'min_participants' => 2,
                'max_participants' => 12,
                'status' => 'open',
                'visibility' => 'public',
                'allow_waitlist' => true,
                'allow_friend_invitations' => true,
                'require_approval' => false,
                'allow_join_by_link' => true,
                'password_hash' => null,
                'minimum_age' => null,
                'rules' => json_encode(['Bring water', 'No headphones recommended']),
                'equipment_requirements' => 'Running shoes and a water bottle',
                'notes' => 'Meet at the south entrance 15 minutes before start.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach ([$demo->id, $alice->id, $bryan->id] as $participantId) {
            DB::table('activity_participants')->updateOrInsert(
                ['activity_id' => $activityId, 'user_id' => $participantId],
                [
                    'id' => (string) Str::uuid(),
                    'role' => $participantId === $demo->id ? 'host' : 'member',
                    'status' => 'joined',
                    'joined_at' => $now,
                    'left_at' => null,
                    'waitlist_position' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('activity_likes')->updateOrInsert(
            ['activity_id' => $activityId, 'user_id' => $alice->id],
            ['id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now],
        );

        $commentId = DB::table('activity_comments')
            ->where('activity_id', $activityId)
            ->where('user_id', $bryan->id)
            ->value('id') ?? (string) Str::uuid();
        DB::table('activity_comments')->updateOrInsert(
            ['id' => $commentId],
            [
                'activity_id' => $activityId,
                'user_id' => $bryan->id,
                'body' => 'I will bring an extra towel and a portable speaker for the warm-up playlist.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('activity_notification_dispatches')->updateOrInsert(
            ['activity_id' => $activityId, 'user_id' => $demo->id, 'type' => 'reminder'],
            [
                'id' => (string) Str::uuid(),
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('hidden_activities')->updateOrInsert(
            ['activity_id' => $activityId, 'user_id' => $mila->id],
            ['id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now],
        );

        DB::table('reports')->updateOrInsert(
            ['reporter_id' => $demo->id, 'reportable_type' => 'App\\Models\\Activity', 'reportable_id' => $activityId, 'reason' => 'spam'],
            [
                'id' => (string) Str::uuid(),
                'details' => 'The booking message was repetitive and not relevant to the public event.',
                'status' => 'reviewed',
                'reviewed_by' => $alice->id,
                'reviewed_at' => $now,
                'resolution' => 'No action taken after review.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $clanId = DB::table('clans')->where('slug', 'city-runners')->value('id') ?? (string) Str::uuid();
        DB::table('clans')->updateOrInsert(
            ['slug' => 'city-runners'],
            [
                'id' => $clanId,
                'owner_id' => $demo->id,
                'name' => 'City Runners',
                'slug' => 'city-runners',
                'description' => 'A supportive Saturday running community for casual and competitive runners.',
                'avatar_path' => null,
                'cover_path' => null,
                'visibility' => 'public',
                'join_policy' => 'approval',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $ownerRoleId = DB::table('clan_roles')
            ->where('clan_id', $clanId)
            ->where('slug', 'owner')
            ->value('id') ?? (string) Str::uuid();
        DB::table('clan_roles')->updateOrInsert(
            ['clan_id' => $clanId, 'slug' => 'owner'],
            [
                'id' => $ownerRoleId,
                'clan_id' => $clanId,
                'name' => 'Owner',
                'slug' => 'owner',
                'color' => '#F59E0B',
                'sort_order' => 1,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('clan_role_permissions')->insertOrIgnore([
            ['clan_role_id' => $ownerRoleId, 'permission' => 'manage_clan', 'created_at' => $now, 'updated_at' => $now],
            ['clan_role_id' => $ownerRoleId, 'permission' => 'approve_members', 'created_at' => $now, 'updated_at' => $now],
            ['clan_role_id' => $ownerRoleId, 'permission' => 'create_announcements', 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach ([$demo->id, $alice->id, $bryan->id] as $memberId) {
            DB::table('clan_members')->updateOrInsert(
                ['clan_id' => $clanId, 'user_id' => $memberId],
                [
                    'id' => (string) Str::uuid(),
                    'clan_id' => $clanId,
                    'user_id' => $memberId,
                    'clan_role_id' => $memberId === $demo->id ? $ownerRoleId : null,
                    'invited_by' => $demo->id,
                    'status' => 'active',
                    'joined_at' => $now,
                    'left_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('clan_announcements')->updateOrInsert(
            ['clan_id' => $clanId, 'title' => 'Next weekend event'],
            [
                'id' => (string) Str::uuid(),
                'clan_id' => $clanId,
                'author_id' => $demo->id,
                'title' => 'Next weekend event',
                'body' => 'We will meet at Benjakitti Park at 6:30 AM for a 5K group run following by breakfast together.',
                'is_pinned' => true,
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('clan_events')->updateOrInsert(
            ['activity_id' => $activityId],
            [
                'id' => (string) Str::uuid(),
                'clan_id' => $clanId,
                'activity_id' => $activityId,
                'created_by' => $demo->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $conversationId = DB::table('conversations')
            ->where('direct_key', $this->pairKey($demo->id, $alice->id))
            ->value('id') ?? (string) Str::uuid();
        DB::table('conversations')->updateOrInsert(
            ['direct_key' => $this->pairKey($demo->id, $alice->id)],
            [
                'id' => $conversationId,
                'type' => 'direct',
                'clan_id' => null,
                'name' => null,
                'created_by' => $demo->id,
                'direct_key' => $this->pairKey($demo->id, $alice->id),
                'description' => 'Chat between Demo User and Alice Park.',
                'last_message_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        foreach ([$demo->id, $alice->id] as $conversationUserId) {
            DB::table('conversation_members')->updateOrInsert(
                ['conversation_id' => $conversationId, 'user_id' => $conversationUserId],
                [
                    'id' => (string) Str::uuid(),
                    'conversation_id' => $conversationId,
                    'user_id' => $conversationUserId,
                    'role' => $conversationUserId === $demo->id ? 'admin' : 'member',
                    'status' => 'active',
                    'joined_at' => $now,
                    'last_read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $messageId = DB::table('messages')
            ->where('conversation_id', $conversationId)
            ->where('sender_id', $demo->id)
            ->where('body', 'Hey Alice, are you still joining the sunrise run this weekend?')
            ->value('id') ?? (string) Str::uuid();
        DB::table('messages')->updateOrInsert(
            ['id' => $messageId],
            [
                'conversation_id' => $conversationId,
                'sender_id' => $demo->id,
                'type' => 'text',
                'body' => 'Hey Alice, are you still joining the sunrise run this weekend?',
                'reply_to_id' => null,
                'edited_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('message_reactions')->updateOrInsert(
            ['message_id' => $messageId, 'user_id' => $alice->id, 'reaction' => 'like'],
            ['id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now],
        );

        DB::table('message_reads')->updateOrInsert(
            ['message_id' => $messageId, 'user_id' => $alice->id],
            [
                'id' => (string) Str::uuid(),
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('message_mentions')->updateOrInsert(
            ['message_id' => $messageId, 'user_id' => $alice->id],
            ['id' => (string) Str::uuid(), 'created_at' => $now, 'updated_at' => $now],
        );

        DB::table('notifications')->updateOrInsert(
            ['notifiable_type' => User::class, 'notifiable_id' => $demo->id, 'type' => 'App\\Notifications\\FriendRequestAccepted'],
            [
                'id' => (string) Str::uuid(),
                'data' => json_encode(['message' => 'Alice accepted your friend request.', 'badge' => 1]),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    protected function pairKey(string $left, string $right): string
    {
        return UserPair::key($left, $right);
    }
}
