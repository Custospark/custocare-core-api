<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds receiving_facility_id to support cross-facility referrals
     * and makes referring_staff_id nullable for facility-to-facility referrals.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            // Add receiving facility FK
            $table->foreignId('receiving_facility_id')
                ->nullable()
                ->after('facility_id')
                ->constrained('facilities')
                ->nullOnDelete();

            // Make referring_staff_id nullable (facility→facility referrals may not have a specific referrer)
            $table->foreignId('referring_staff_id')
                ->nullable()
                ->change();

            // Indexes for filtering by receiving facility and cross-facility queries
            $table->index('receiving_facility_id');
            $table->index(['facility_id', 'receiving_facility_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign(['receiving_facility_id']);
            $table->dropColumn('receiving_facility_id');

            // Revert referring_staff_id back to required
            $table->foreignId('referring_staff_id')
                ->constrained('staff')
                ->onDelete('cascade')
                ->change();

            $table->dropIndex(['receiving_facility_id']);
            $table->dropIndex(['facility_id', 'receiving_facility_id']);
        });
    }
};