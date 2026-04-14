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
        Schema::table('facilities', function (Blueprint $table) {
            // Add status fields to facilities table
            $table->enum('status', ['active', 'suspended', 'banned'])
                  ->default('active')
                  ->after('operational_status')
                  ->comment('Facility account status');
            
            $table->text('status_reason')
                  ->nullable()
                  ->after('status')
                  ->comment('Reason for current status (especially for suspended/banned)');
            
            $table->timestamp('status_set_at')
                  ->nullable()
                  ->after('status_reason')
                  ->comment('When the current status was set');
            
            $table->unsignedBigInteger('status_set_by')
                  ->nullable()
                  ->after('status_set_at')
                  ->comment('Platform administrator who set the current status');
            
            // Add foreign key constraint for status_set_by
            $table->foreign('status_set_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['status_set_by']);
            
            // Drop the columns
            $table->dropColumn([
                'status',
                'status_reason',
                'status_set_at',
                'status_set_by'
            ]);
        });
    }
};