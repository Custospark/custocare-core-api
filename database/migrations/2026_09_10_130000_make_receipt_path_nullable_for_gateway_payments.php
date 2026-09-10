<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gateway (online) payments carry no uploaded proof file at creation -
     * the receipt is generated on approval instead. The NOT NULL imposed for
     * the manual proof-upload flow (2026_05_29_000002) rejects every gateway
     * initiate with 1364, so relax the column back to nullable.
     *
     * Forward-only and non-destructive: no data is modified, existing rows
     * keep their paths.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `payments` MODIFY `receipt_path` VARCHAR(512) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `payments` MODIFY `receipt_path` VARCHAR(512) NOT NULL');
        }
    }
};
