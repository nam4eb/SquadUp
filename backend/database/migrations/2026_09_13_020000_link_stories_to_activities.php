<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table): void {
            $table->foreignUuid('activity_id')->nullable()->after('user_id')
                ->constrained('activities')->nullOnDelete();
            $table->index(['activity_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stories', fn (Blueprint $table) => $table->dropConstrainedForeignId('activity_id'));
    }
};
