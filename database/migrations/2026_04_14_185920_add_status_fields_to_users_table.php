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
        Schema::table('users', function (Blueprint $table) {
            // Add status fields to users table
            $table->enum('status', ['active', 'suspended', 'banned'])
                  ->default('active')
                  ->after('identity_state')
                  ->comment('User account status');
            
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
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            
            
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