<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->foreignUuid('recurrence_parent_id')->nullable()->after('host_id')
                ->constrained('activities')->nullOnDelete();
            $table->unsignedSmallInteger('recurrence_index')->nullable()->after('recurrence_parent_id');
            $table->json('recurrence_rule')->nullable()->after('recurrence_index');
            $table->index(['recurrence_parent_id', 'starts_at']);
        });
        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreignUuid('activity_id')->nullable()->unique()->after('clan_id')
                ->constrained('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('activity_id');
        });
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn(['recurrence_index', 'recurrence_rule']);
        });
    }
};
