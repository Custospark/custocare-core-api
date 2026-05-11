<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_feedback_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_feedback_request_id')->constrained('hub_feedback_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['hub_feedback_request_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_feedback_votes');
    }
};
