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
        // First, update existing records
        DB::table('prescriptions')
            ->where('status', 'Draft - Not Yet Finalized')
            ->update(['status' => 'Active - Ready for Dispensing']);
        
        // Then modify the column to remove the old option and set new default
        DB::statement("ALTER TABLE prescriptions MODIFY COLUMN status ENUM(
            'Active - Ready for Dispensing',
            'Partially Dispensed',
            'Fully Dispensed',
            'Expired - Past Valid Date',
            'Cancelled - No Longer Valid',
            'On Hold - Pending Review'
        ) NOT NULL DEFAULT 'Active - Ready for Dispensing'");
    }

    public function down(): void
    {
        // Revert to old enum values
        DB::statement("ALTER TABLE prescriptions MODIFY COLUMN status ENUM(
            'Draft - Not Yet Finalized',
            'Active - Ready for Dispensing',
            'Partially Dispensed',
            'Fully Dispensed',
            'Expired - Past Valid Date',
            'Cancelled - No Longer Valid',
            'On Hold - Pending Review'
        ) NOT NULL DEFAULT 'Draft - Not Yet Finalized'");
    }
};