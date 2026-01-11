<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_staff_roles', function (Blueprint $table) {
            $table->string('employee_number', 50)->nullable()->after('staff_id');
            $table->unique(['facility_id', 'employee_number']);

        });
    }

    public function down(): void
    {
        Schema::table('facility_staff_roles', function (Blueprint $table) {
            $table->dropIndex(['employee_number']);
            $table->dropColumn('employee_number');
        });
    }
};

