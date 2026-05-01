<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lab_request_items', function (Blueprint $table) {
            // Add started_by_staff_id - who started processing
            if (!Schema::hasColumn('lab_request_items', 'started_by_staff_id')) {
                $table->unsignedBigInteger('started_by_staff_id')->nullable()->after('started_at');
            }
            
            // Add completed_by_staff_id - who marked as completed
            if (!Schema::hasColumn('lab_request_items', 'completed_by_staff_id')) {
                $table->unsignedBigInteger('completed_by_staff_id')->nullable()->after('completed_at');
            }
            
            // Add cancelled_by_staff_id - who cancelled the test
            if (!Schema::hasColumn('lab_request_items', 'cancelled_by_staff_id')) {
                $table->unsignedBigInteger('cancelled_by_staff_id')->nullable()->after('cancelled_at');
            }
            
            // Add foreign key constraints
            if (!Schema::hasColumn('lab_request_items', 'started_by_staff_id')) {
                $table->foreign('started_by_staff_id')
                      ->references('id')
                      ->on('staff')
                      ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('lab_request_items', 'completed_by_staff_id')) {
                $table->foreign('completed_by_staff_id')
                      ->references('id')
                      ->on('staff')
                      ->onDelete('set null');
            }
            
            if (!Schema::hasColumn('lab_request_items', 'cancelled_by_staff_id')) {
                $table->foreign('cancelled_by_staff_id')
                      ->references('id')
                      ->on('staff')
                      ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('lab_request_items', function (Blueprint $table) {
            // Drop foreign keys
            if (Schema::hasColumn('lab_request_items', 'started_by_staff_id')) {
                $table->dropForeign(['started_by_staff_id']);
            }
            
            if (Schema::hasColumn('lab_request_items', 'completed_by_staff_id')) {
                $table->dropForeign(['completed_by_staff_id']);
            }
            
            if (Schema::hasColumn('lab_request_items', 'cancelled_by_staff_id')) {
                $table->dropForeign(['cancelled_by_staff_id']);
            }
            
            // Drop columns
            if (Schema::hasColumn('lab_request_items', 'started_by_staff_id')) {
                $table->dropColumn('started_by_staff_id');
            }
            
            if (Schema::hasColumn('lab_request_items', 'completed_by_staff_id')) {
                $table->dropColumn('completed_by_staff_id');
            }
            
            if (Schema::hasColumn('lab_request_items', 'cancelled_by_staff_id')) {
                $table->dropColumn('cancelled_by_staff_id');
            }
        });
    }
};