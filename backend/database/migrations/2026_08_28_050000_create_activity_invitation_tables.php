<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignUuid('inviter_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('invitee_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'invitee_id']);
            $table->index(['invitee_id', 'status', 'created_at']);
        });

        Schema::create('activity_invitation_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 12);
            $table->char('secret_hash', 64)->unique();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['activity_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_invitation_tokens');
        Schema::dropIfExists('activity_invitations');
    }
};
