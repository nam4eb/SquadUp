<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clan_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('clan_id')->constrained('clans')->cascadeOnDelete();
            $table->foreignUuid('activity_id')->unique()->constrained('activities')->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['clan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clan_events');
    }
};
