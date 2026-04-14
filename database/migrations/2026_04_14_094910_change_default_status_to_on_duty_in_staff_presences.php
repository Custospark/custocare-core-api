<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update the column definition to change the default value
        Schema::table('staff_presences', function (Blueprint $table) {
            $table->enum('status', [
                'off_duty',
                'on_duty',
                'on_break',
                'busy',
                'unavailable',
            ])->default('on_duty')->change();
        });

        // Optional: Update existing records where status is NULL or 'off_duty'
        // to 'on_duty' if they are currently active (ended_at is NULL)
        DB::table('staff_presences')
            ->whereNull('ended_at')
            ->where(function ($query) {
                $query->whereNull('status')
                      ->orWhere('status', 'off_duty');
            })
            ->update(['status' => 'on_duty']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the default value back to 'off_duty'
        Schema::table('staff_presences', function (Blueprint $table) {
            $table->enum('status', [
                'off_duty',
                'on_duty',
                'on_break',
                'busy',
                'unavailable',
            ])->default('off_duty')->change();
        });

        // Optional: Revert active records back to 'off_duty'
        DB::table('staff_presences')
            ->whereNull('ended_at')
            ->where('status', 'on_duty')
            ->update(['status' => 'off_duty']);
    }
};