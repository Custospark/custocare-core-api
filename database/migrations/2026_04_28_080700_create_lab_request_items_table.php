<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_request_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('item_uuid')->unique()->index()->comment('Public-facing identifier');

            // Relationships
            $table->unsignedBigInteger('lab_request_id')->index();
            $table->unsignedBigInteger('lab_test_id')->index();

            // Workflow status per test
            $table->enum('status', [
                'pending',
                'sample_collected',
                'in_progress',
                'completed',
                'verified',
                'cancelled'
            ])->default('pending')->index();

            // Sample tracking
            $table->string('sample_type', 100)->nullable()->comment('Blood, urine, stool, etc.');
            $table->string('sample_identifier', 100)->nullable()->comment('Barcode or lab sample ID');
            $table->timestamp('collected_at')->nullable()->index();
            $table->unsignedBigInteger('collected_by_staff_id')->nullable();

            // Processing tracking
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();

            // Verification
            $table->unsignedBigInteger('verified_by_staff_id')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Result summary (denormalized for quick display)
            $table->enum('result_flag', [
                'normal',
                'abnormal',
                'critical',
                'pending'
            ])->default('pending')->index();

            // Notes
            $table->text('notes')->nullable();

            // Cancellation
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();

            // Foreign keys
            $table->foreign('lab_request_id')->references('id')->on('lab_requests')->onDelete('cascade');
            $table->foreign('lab_test_id')->references('id')->on('lab_tests')->onDelete('restrict');
            $table->foreign('collected_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('verified_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('created_by_staff_id')->references('id')->on('staff')->onDelete('set null');
            $table->foreign('updated_by_staff_id')->references('id')->on('staff')->onDelete('set null');

            // Performance indexes
            $table->index(['lab_request_id', 'status']);
            $table->index(['lab_test_id', 'status']);
            $table->index(['status', 'collected_at']);
            $table->index(['status', 'completed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_request_items');
    }
};