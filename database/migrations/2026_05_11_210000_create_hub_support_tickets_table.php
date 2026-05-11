<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_support_tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('category', 32);
            $table->string('priority', 16)->default('medium');

            $table->string('subject', 200);
            $table->text('body');

            $table->string('status', 32)->default('submitted');
            $table->text('staff_reply')->nullable();

            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_support_tickets');
    }
};

