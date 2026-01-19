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
        Schema::table('service_catalogs', function (Blueprint $table) {
            // Add columns
            $table->unsignedBigInteger('facility_id')->index()->after('service_uuid');
            $table->string('currency_code', 3)->default('UGX')->index();
            $table->decimal('price_amount', 12, 2)->default(0);

            // Add foreign key
            $table->foreign('facility_id')
                ->references('id')
                ->on('facilities')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['facility_id']);

            // Drop indexes
            $table->dropIndex(['facility_id']);
            $table->dropIndex(['currency_code']);

            // Drop columns
            $table->dropColumn([
                'facility_id',
                'currency_code',
                'price_amount'
            ]);
        });
    }
};
