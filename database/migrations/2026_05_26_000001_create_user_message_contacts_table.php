<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_message_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('display_name', 150);
            $table->foreignId('linked_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('email_encrypted')->nullable();
            $table->string('email_hash', 128)->nullable();
            $table->text('phone_encrypted')->nullable();
            $table->string('phone_hash', 128)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'display_name']);
            $table->unique(['owner_user_id', 'email_hash'], 'umc_owner_email_unique');
            $table->unique(['owner_user_id', 'phone_hash'], 'umc_owner_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_message_contacts');
    }
};
