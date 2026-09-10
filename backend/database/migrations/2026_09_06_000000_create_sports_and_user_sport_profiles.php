<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('name', 100);
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('min_players')->default(2);
            $table->unsignedSmallInteger('max_players')->default(4);
            $table->boolean('supports_team')->default(false);
            $table->boolean('supports_singles')->default(true);
            $table->boolean('supports_doubles')->default(true);
            $table->string('skill_system', 40)->default('squadup_rating');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        Schema::create('user_sport_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sport_id')->constrained()->cascadeOnDelete();
            $table->string('self_declared_level', 32);
            $table->string('verified_level', 32)->nullable();
            $table->unsignedSmallInteger('skill_rating')->default(800);
            $table->unsignedInteger('matches_played')->default(0);
            $table->decimal('rating_confidence', 5, 4)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'sport_id']);
            $table->index(['sport_id', 'skill_rating']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('onboarding_step', 40)->default('profile')->after('account_status');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_step');
            $table->string('default_city', 120)->nullable()->after('location');
            $table->decimal('default_area_latitude', 10, 7)->nullable()->after('default_city');
            $table->decimal('default_area_longitude', 10, 7)->nullable()->after('default_area_latitude');
            $table->string('timezone', 64)->default('UTC')->after('default_area_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'onboarding_step', 'onboarding_completed_at', 'default_city',
                'default_area_latitude', 'default_area_longitude', 'timezone',
            ]);
        });
        Schema::dropIfExists('user_sport_profiles');
        Schema::dropIfExists('sports');
    }
};
