<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_community_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 40)->index()
                ->comment('discussion|feature_idea|product_update');
            $table->string('title', 255);
            $table->text('body');
            $table->unsignedInteger('comments_count')->default(0);
            $table->timestamps();

            $table->index(['channel', 'created_at']);
        });

        Schema::create('hub_community_comments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hub_community_post_id')
                ->constrained('hub_community_posts')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['hub_community_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_community_comments');
        Schema::dropIfExists('hub_community_posts');
    }
};
