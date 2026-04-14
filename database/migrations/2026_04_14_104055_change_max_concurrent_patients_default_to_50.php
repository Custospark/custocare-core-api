<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change the default value for max_concurrent_patients column
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_concurrent_patients')
                  ->default(50)
                  ->change();
        });

        // Optional: Update existing records that currently have the default value (10)
        // to the new default (50) if they haven't been customized
        DB::table('staff')
            ->where('max_concurrent_patients', 10)
            ->update(['max_concurrent_patients' => 50]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to the original default value
        Schema::table('staff', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_concurrent_patients')
                  ->default(10)
                  ->change();
        });

        // Optional: Revert records back to 10 if they were updated by this migration
        DB::table('staff')
            ->where('max_concurrent_patients', 50)
            ->update(['max_concurrent_patients' => 10]);
    }
};