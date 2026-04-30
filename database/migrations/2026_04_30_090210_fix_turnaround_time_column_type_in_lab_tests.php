<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            // Change from unsignedSmallInteger to decimal
            $table->decimal('turnaround_time_hours', 5, 2)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('lab_tests', function (Blueprint $table) {
            // Revert back (will lose decimal precision)
            $table->unsignedSmallInteger('turnaround_time_hours')->nullable()->change();
        });
    }
};