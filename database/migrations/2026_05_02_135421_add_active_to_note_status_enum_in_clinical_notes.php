<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL syntax to modify enum
        DB::statement("ALTER TABLE clinical_notes MODIFY COLUMN note_status ENUM('draft', 'final', 'amended', 'cancelled', 'active') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        // Revert to original enum
        DB::statement("ALTER TABLE clinical_notes MODIFY COLUMN note_status ENUM('draft', 'final', 'amended', 'cancelled') NOT NULL DEFAULT 'active'");
    }
};