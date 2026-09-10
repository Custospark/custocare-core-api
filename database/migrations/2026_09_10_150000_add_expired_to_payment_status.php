<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the expired terminal state (user-cancelled or TTL sweeper).
     * Forward-only, additive: existing values untouched.
     * No transaction wrapper: MySQL implicit-commits DDL.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','completed','failed','expired','refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
    }
};
