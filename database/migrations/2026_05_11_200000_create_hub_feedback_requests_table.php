<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_feedback_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category', 32);
            $table->string('subject', 200);
            $table->text('body');
            $table->string('status', 32)->default('submitted');
            $table->boolean('include_in_roadmap')->default(false);
            $table->text('admin_internal_notes')->nullable();
            $table->text('staff_reply')->nullable();
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['include_in_roadmap', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_feedback_requests');
    }
};
