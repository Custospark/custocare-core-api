<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->after('plan_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->string('billing_cycle', 20)->default('monthly')->change();
        });
    }
};
