<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_notification_dispatches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->timestampTz('sent_at');
            $table->timestamps();
            $table->unique(['activity_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_notification_dispatches');
    }
};
