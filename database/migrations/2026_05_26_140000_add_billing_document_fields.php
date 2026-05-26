<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_id')->nullable()->after('facility_id');
            $table->foreign('payment_id')
                ->references('id')->on('payments')
                ->nullOnDelete();
            $table->unique('payment_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('receipt_number', 50)->nullable()->unique()->after('transaction_reference');
            $table->unsignedBigInteger('invoice_id')->nullable()->after('subscription_id');
            $table->foreign('invoice_id')
                ->references('id')->on('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['receipt_number', 'invoice_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn('payment_id');
        });
    }
};
