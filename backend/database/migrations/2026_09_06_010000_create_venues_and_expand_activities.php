<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 160);
            $table->string('address', 500);
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->boolean('is_verified')->default(false)->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
            $table->index(['city', 'district']);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->foreignUuid('sport_id')->nullable()->after('topic_id')->constrained('sports')->nullOnDelete();
            $table->foreignUuid('venue_id')->nullable()->after('sport_id')->constrained('venues')->nullOnDelete();
            $table->unsignedSmallInteger('skill_min')->nullable()->after('longitude');
            $table->unsignedSmallInteger('skill_max')->nullable()->after('skill_min');
            $table->string('match_format', 24)->nullable()->after('skill_max');
            $table->decimal('fee', 12, 2)->nullable()->after('match_format');
            $table->char('currency', 3)->nullable()->after('fee');

            $table->index(['sport_id', 'status', 'starts_at']);
            $table->index(['venue_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('venue_id');
            $table->dropConstrainedForeignId('sport_id');
            $table->dropColumn(['skill_min', 'skill_max', 'match_format', 'fee', 'currency']);
        });
        Schema::dropIfExists('venues');
    }
};
