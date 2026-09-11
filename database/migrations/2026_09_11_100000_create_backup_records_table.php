<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backup evidence log (Guidelines: automated backups, offsite storage,
     * monthly restore tests). Every dump - deploy-time or scheduled - gets
     * a row: path, size, restore-test outcome. The review job flags gaps.
     * Forward-only, additive.
     */
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('backup_uuid')->unique()->index();
            $table->string('environment', 20)->index()->comment('staging|production');
            $table->string('path', 512);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->enum('kind', ['manual', 'deploy', 'scheduled'])->default('manual')->index();
            $table->timestamp('taken_at')->index();
            $table->timestamp('restore_tested_at')->nullable();
            $table->boolean('restore_ok')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
