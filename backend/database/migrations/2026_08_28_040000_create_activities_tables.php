<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('host_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('activity_categories')->restrictOnDelete();
            $table->foreignUuid('topic_id')->constrained('activity_topics')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('location_name', 160);
            $table->string('location_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('min_participants')->default(1);
            $table->unsignedSmallInteger('max_participants');
            $table->string('status', 20)->default('open')->index();
            $table->string('visibility', 20)->default('public')->index();
            $table->boolean('allow_waitlist')->default(true);
            $table->boolean('allow_friend_invitations')->default(true);
            $table->boolean('require_approval')->default(false);
            $table->boolean('allow_join_by_link')->default(false);
            $table->string('password_hash')->nullable();
            $table->unsignedSmallInteger('minimum_age')->nullable();
            $table->json('rules')->nullable();
            $table->text('equipment_requirements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visibility', 'starts_at']);
            $table->index(['host_id', 'starts_at']);
            $table->index(['category_id', 'topic_id', 'starts_at']);
        });

        Schema::create('activity_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 20)->default('member');
            $table->string('status', 20)->default('joined')->index();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'user_id']);
            $table->index(['activity_id', 'status', 'joined_at']);
            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_participants');
        Schema::dropIfExists('activities');
    }
};
