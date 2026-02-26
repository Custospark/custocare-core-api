<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_mfa_type_to_account_recovery_tokens.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL doesn't support modifying enums directly, so we need to alter the column
        DB::statement("ALTER TABLE account_recovery_tokens MODIFY COLUMN type ENUM('email_verification', 'password_reset', 'account_recovery', 'mfa_verification') NOT NULL");
    }

    public function down(): void
    {
        // Revert back to original enum
        DB::statement("ALTER TABLE account_recovery_tokens MODIFY COLUMN type ENUM('email_verification', 'password_reset', 'account_recovery') NOT NULL");
    }
};