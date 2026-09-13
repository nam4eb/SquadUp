<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ratings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('rater_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('sportsmanship');
            $table->unsignedTinyInteger('skill');
            $table->unsignedTinyInteger('reliability');
            $table->string('comment', 500)->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'rater_id', 'ratee_id']);
            $table->index(['ratee_id', 'created_at']);
            $table->index(['sport_id', 'ratee_id']);
        });

        Schema::create('user_reputations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->decimal('sportsmanship_average', 3, 2)->default(0);
            $table->decimal('skill_average', 3, 2)->default(0);
            $table->decimal('reliability_average', 3, 2)->default(0);
            $table->unsignedInteger('attended_count')->default(0);
            $table->unsignedInteger('absent_count')->default(0);
            $table->decimal('attendance_rate', 5, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reputations');
        Schema::dropIfExists('activity_ratings');
    }
};
