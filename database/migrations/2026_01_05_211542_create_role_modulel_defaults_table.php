<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_module_defaults', function (Blueprint $table) {
            $table->id();
            $table->string('role_code')->index()->comment('Role code (physician, nurse, patient, super_admin, etc.)');
            $table->string('module_code')->index()->comment('Module code');
            $table->boolean('default_access')->default(false)->comment('Default access for this role');
            $table->timestamps();

            $table->unique(['role_code', 'module_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_module_defaults');
    }
};
