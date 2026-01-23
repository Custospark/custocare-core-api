<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_tokens', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->index();

            // Store ONLY a hash of the raw token (never store raw token)
            $table->string('token_hash', 128)->unique()->index();

            // Expiry + single-use
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable()->index();

            // Optional metadata
            $table->enum('channel', ['email', 'sms'])->nullable()->index();
            $table->unsignedBigInteger('created_by_staff_id')->nullable()->index();
            $table->string('created_ip', 45)->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Helpful composite indexes
            $table->index(['user_id', 'expires_at']);
            $table->index(['user_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tokens');
    }
};
