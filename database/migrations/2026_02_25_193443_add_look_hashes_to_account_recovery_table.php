<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_lookup_hashes_to_account_recovery_tokens.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_recovery_tokens', function (Blueprint $table) {
            $table->string('token_hash_lookup', 64)->nullable()->index();
            $table->string('otp_hash_lookup', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('account_recovery_tokens', function (Blueprint $table) {
            $table->dropColumn(['token_hash_lookup', 'otp_hash_lookup']);
        });
    }
};