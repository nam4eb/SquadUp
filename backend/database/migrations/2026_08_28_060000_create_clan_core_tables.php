<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('visibility', 20)->default('public')->index();
            $table->string('join_policy', 20)->default('approval')->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clan_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 90);
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['clan_id', 'slug']);
        });

        Schema::create('clan_role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('clan_role_id')->constrained('clan_roles')->cascadeOnDelete();
            $table->string('permission', 80);
            $table->timestamps();
            $table->unique(['clan_role_id', 'permission']);
        });

        Schema::create('clan_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('clan_role_id')->nullable()->constrained('clan_roles')->restrictOnDelete();
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('requested')->index();
            $table->timestampTz('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->timestamps();
            $table->unique(['clan_id', 'user_id']);
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['clan_id', 'status', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_members');
        Schema::dropIfExists('clan_role_permissions');
        Schema::dropIfExists('clan_roles');
        Schema::dropIfExists('clans');
    }
};
