<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Add updated_by_staff_id column after a relevant column (e.g., created_by_staff_id)
            $table->unsignedBigInteger('updated_by_staff_id')
                  ->nullable()
                  ->after('created_by_staff_id')
                  ->comment('Staff ID who last updated this inventory item');
                  
            // Add foreign key constraint if staff table exists
            $table->foreign('updated_by_staff_id')
                  ->references('id')
                  ->on('staff')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['updated_by_staff_id']);
            
            // Then drop the column
            $table->dropColumn('updated_by_staff_id');
        });
    }
};