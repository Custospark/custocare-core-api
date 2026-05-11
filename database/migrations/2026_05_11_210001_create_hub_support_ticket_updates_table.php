<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_support_ticket_updates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hub_support_ticket_id')->constrained('hub_support_tickets')->cascadeOnDelete();

            $table->string('status', 32);
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['hub_support_ticket_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_support_ticket_updates');
    }
};

