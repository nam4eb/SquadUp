<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ratings', function (Blueprint $table): void {
            $table->timestamp('invalidated_at')->nullable()->after('comment');
            $table->foreignUuid('invalidated_by')->nullable()->after('invalidated_at')
                ->constrained('users')->nullOnDelete();
            $table->string('invalidation_reason', 500)->nullable()->after('invalidated_by');
            $table->index(['ratee_id', 'invalidated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_ratings', function (Blueprint $table): void {
            $table->dropIndex(['ratee_id', 'invalidated_at']);
            $table->dropConstrainedForeignId('invalidated_by');
            $table->dropColumn(['invalidated_at', 'invalidation_reason']);
        });
    }
};
