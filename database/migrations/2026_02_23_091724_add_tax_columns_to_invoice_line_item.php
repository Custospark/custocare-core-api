// database/migrations/xxxx_add_tax_columns_to_invoice_line_items.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            // Add missing tax columns
            $table->decimal('adjustment_tax_amount', 10, 2)->nullable()->after('adjustment_amount');
            $table->decimal('adjustment_total_amount', 10, 2)->nullable()->after('adjustment_tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_line_items', function (Blueprint $table) {
            $table->dropColumn(['adjustment_tax_amount', 'adjustment_total_amount']);
        });
    }
};