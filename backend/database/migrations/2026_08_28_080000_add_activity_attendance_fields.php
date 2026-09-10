<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_participants', function (Blueprint $table): void {
            $table->timestampTz('attendance_marked_at')->nullable()->after('left_at');
            $table->foreignUuid('attendance_marked_by')->nullable()->after('attendance_marked_at')
                ->constrained('users')->nullOnDelete();
            $table->index(['activity_id', 'status', 'attendance_marked_at'], 'activity_attendance_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_participants', function (Blueprint $table): void {
            $table->dropIndex('activity_attendance_index');
            $table->dropConstrainedForeignId('attendance_marked_by');
            $table->dropColumn('attendance_marked_at');
        });
    }
};
