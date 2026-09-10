<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('media_disk', 40);
            $table->string('media_path', 500);
            $table->string('media_type', 20)->default('image');
            $table->string('caption', 500)->nullable();
            $table->string('visibility', 20)->default('friends');
            $table->timestampTz('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'expires_at', 'created_at']);
        });

        Schema::create('story_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('viewed_at');
            $table->timestamps();
            $table->unique(['story_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
    }
};
