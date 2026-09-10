<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('activity_categories')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name', 100);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('status', 20)->default('active')->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['category_id', 'slug']);
            $table->index(['category_id', 'status', 'is_visible', 'sort_order'], 'activity_topics_listing_index');
        });

        Schema::table('activity_topics', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('activity_topics')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_topics');
        Schema::dropIfExists('activity_categories');
    }
};
