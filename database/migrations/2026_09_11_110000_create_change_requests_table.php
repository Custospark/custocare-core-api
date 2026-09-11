<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signed change-request log (EMR Guidelines: all changes documented and
     * tracked with signed request forms, test results attached). Every
     * production/staging deploy records who requested, who approved, what
     * commits shipped, and how to roll back. Forward-only, additive.
     */
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique()->index();
            $table->string('title', 255);
            $table->enum('environment', ['staging', 'production'])->index();
            $table->string('commit_range', 255)->comment('e.g. a662231..1380f5b');
            $table->string('requested_by', 150);
            $table->string('approved_by', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->string('rollback_ref', 255)->nullable()
                ->comment('Commit, backup path or dump to restore');
            $table->text('test_evidence')->nullable()
                ->comment('vera/tests/verification summary at deploy time');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
