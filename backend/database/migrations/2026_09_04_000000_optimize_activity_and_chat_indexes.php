<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            // Keep messages_cursor_index; this is the duplicate created by the initial schema.
            $table->dropIndex('messages_conversation_id_created_at_id_index');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->index(
                ['last_message_at', 'updated_at'],
                'conversations_recent_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_recent_index');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->index(
                ['conversation_id', 'created_at', 'id'],
                'messages_conversation_id_created_at_id_index',
            );
        });
    }
};

