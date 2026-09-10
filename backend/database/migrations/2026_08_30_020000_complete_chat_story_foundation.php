<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->uuid('client_message_id')->nullable()->after('sender_id');
            $table->foreignUuid('story_id')->nullable()->after('reply_to_id')
                ->constrained('stories')->nullOnDelete();
            $table->unique(
                ['conversation_id', 'sender_id', 'client_message_id'],
                'messages_client_dedup_unique',
            );
            $table->index(['conversation_id', 'created_at', 'id'], 'messages_cursor_index');
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->string('thumbnail_path', 500)->nullable()->after('media_path');
            $table->unsignedInteger('duration_ms')->nullable()->after('media_type');
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table): void {
            $table->dropColumn(['thumbnail_path', 'duration_ms']);
        });
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_cursor_index');
            $table->dropUnique('messages_client_dedup_unique');
            $table->dropConstrainedForeignId('story_id');
            $table->dropColumn('client_message_id');
        });
    }
};
