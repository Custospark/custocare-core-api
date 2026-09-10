<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Standardize on gateway vocabulary (shared with Custosell):
     * approved -> completed, rejected -> failed.
     *
     * Forward-only: existing rows are remapped BEFORE the enum is narrowed,
     * so no row is ever left with an unknown value. No transaction wrapper:
     * MySQL implicit-commits DDL, which breaks Laravel's migration
     * transaction ("There is no active transaction").
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('payments')->where('status', 'approved')->update(['status' => 'completed']);
        DB::table('payments')->where('status', 'rejected')->update(['status' => 'failed']);
        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('payments')->where('status', 'completed')->update(['status' => 'approved']);
        DB::table('payments')->where('status', 'failed')->update(['status' => 'rejected']);
        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending'");
    }
};
