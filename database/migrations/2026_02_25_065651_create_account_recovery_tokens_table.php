<?php
// database/migrations/2024_01_01_000001_create_account_recovery_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_recovery_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token_hash', 255)->unique()->index();
            $table->string('otp_code', 6)->nullable();
            $table->enum('type', ['email_verification', 'password_reset', 'account_recovery']);
            $table->enum('channel', ['email', 'sms'])->default('email');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'type', 'expires_at']);
            $table->index(['otp_code', 'type', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_recovery_tokens');
    }
};