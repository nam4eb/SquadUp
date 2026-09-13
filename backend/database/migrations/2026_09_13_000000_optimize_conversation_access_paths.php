<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'conversation_id'], 'conversation_members_access_index');
        });
        Schema::table('activity_participants', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'activity_id'], 'activity_participants_access_index');
        });
        Schema::table('clan_members', function (Blueprint $table): void {
            $table->index(['user_id', 'status', 'clan_id'], 'clan_members_access_index');
        });
        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['conversation_id', 'created_at'], 'messages_latest_index');
            $table->index(['conversation_id', 'sender_id', 'created_at'], 'messages_unread_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_latest_index');
            $table->dropIndex('messages_unread_index');
        });
        Schema::table('clan_members', fn (Blueprint $table) => $table->dropIndex('clan_members_access_index'));
        Schema::table('activity_participants', fn (Blueprint $table) => $table->dropIndex('activity_participants_access_index'));
        Schema::table('conversation_members', fn (Blueprint $table) => $table->dropIndex('conversation_members_access_index'));
    }
};
