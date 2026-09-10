<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_reasons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 40)->unique();
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        foreach (['spam', 'harassment', 'hate', 'sexual', 'violence', 'misinformation', 'scam', 'fake_account', 'inappropriate_content', 'other'] as $index => $code) {
            DB::table('report_reasons')->insert([
                'id' => (string) Str::uuid(), 'code' => $code,
                'label' => str($code)->replace('_', ' ')->title()->toString(),
                'is_active' => true, 'sort_order' => ($index + 1) * 10,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reasons');
    }
};
