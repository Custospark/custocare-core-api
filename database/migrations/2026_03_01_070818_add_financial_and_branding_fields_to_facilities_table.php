<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('facilities', function (Blueprint $table) {

        /* ======================================================
           🆕 Financial Configuration
        ====================================================== */

        $table->string('currency', 3)
              ->default('USD')
              ->after('timezone');

        $table->boolean('tax_enabled')
              ->default(false)
              ->after('currency');

        $table->string('tax_name')
              ->nullable()
              ->after('tax_enabled');

        $table->decimal('tax_rate', 8, 4)
              ->nullable()
              ->after('tax_name');


        /* ======================================================
           🆕 Branding
        ====================================================== */

        $table->string('facility_logo_path', 512)
              ->nullable()
              ->after('tax_rate');

        $table->string('primary_brand_color', 20)
              ->nullable()
              ->after('facility_logo_path');

        $table->string('secondary_brand_color', 20)
              ->nullable()
              ->after('primary_brand_color');

    });
}

public function down(): void
{
    Schema::table('facilities', function (Blueprint $table) {

        $table->dropColumn([
            'currency',
            'tax_enabled',
            'tax_name',
            'tax_rate',
            'facility_logo_path',
            'primary_brand_color',
            'secondary_brand_color',
        ]);

    });
}
};
