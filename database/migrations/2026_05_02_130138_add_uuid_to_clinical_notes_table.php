<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->uuid('uuid')
                  ->after('id')
                  ->unique()
                  ->nullable(false)
                  ->comment('Public unique identifier for API exposure');
            
            // Add index for faster lookups
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_notes', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};