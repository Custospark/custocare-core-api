<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_materials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            /** External video URL (YouTube, Vimeo, Loom, etc.) */
            $table->string('video_url', 2048);
            /** Cover / card image shown in grids */
            $table->string('thumbnail_url', 2048)->nullable();
            /** Optional secondary hero image */
            $table->string('banner_image_url', 2048)->nullable();
            /**
             * Hub Learning Center tab slug: watch-tutorials | start-training | getting-started | track-progress
             */
            $table->string('category', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'category']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_materials');
    }
};
