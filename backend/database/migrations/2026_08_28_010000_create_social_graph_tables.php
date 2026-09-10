<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friend_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('receiver_id')->constrained('users')->cascadeOnDelete();
            $table->string('pair_key', 73)->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['receiver_id', 'status', 'created_at']);
            $table->index(['sender_id', 'status', 'created_at']);
        });

        Schema::create('friendships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_low_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('user_high_id')->constrained('users')->cascadeOnDelete();
            $table->string('pair_key', 73)->unique();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['user_low_id', 'accepted_at']);
            $table->index(['user_high_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
        Schema::dropIfExists('friend_requests');
    }
};
