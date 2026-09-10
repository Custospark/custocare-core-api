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
     * Forward-only. Order matters under MySQL strict mode: widen the enum
     * FIRST (old + new values), remap rows, then narrow to the final set.
     * Writing a not-yet-listed value throws 1265. No transaction wrapper:
     * MySQL implicit-commits DDL.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','approved','rejected','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
        DB::table('payments')->where('status', 'approved')->update(['status' => 'completed']);
        DB::table('payments')->where('status', 'rejected')->update(['status' => 'failed']);
        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','approved','rejected','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
        DB::table('payments')->where('status', 'completed')->update(['status' => 'approved']);
        DB::table('payments')->where('status', 'failed')->update(['status' => 'rejected']);
        DB::statement("ALTER TABLE `payments` MODIFY `status` ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending'");
    }
};
