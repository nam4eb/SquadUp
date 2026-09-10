<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_leaderboard_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->string('cache_key', 190)->unique();
            $table->string('period', 20);
            $table->string('metric', 32);
            $table->foreignUuid('topic_id')->nullable()->constrained('activity_topics')->nullOnDelete();
            $table->json('payload');
            $table->timestampTz('generated_at');
            $table->timestampTz('expires_at')->index();
            $table->timestamps();

            $table->index(['clan_id', 'period', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_leaderboard_snapshots');
    }
};
