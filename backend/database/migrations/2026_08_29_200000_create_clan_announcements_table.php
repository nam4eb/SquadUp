<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestampTz('published_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['clan_id', 'is_pinned', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_announcements');
    }
};
