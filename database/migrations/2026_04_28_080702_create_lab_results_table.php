<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('result_uuid')->unique()->index()->comment('Public-facing identifier');

            // Core relationships
            $table->unsignedBigInteger('lab_request_item_id')->index();
            $table->unsignedBigInteger('template_field_id')->index();

            // Result value (flexible for all data types)
            $table->text('value')->nullable();

            // Normalization support
            $table->string('unit', 50)->nullable();
            $table->decimal('numeric_value', 12, 4)->nullable()->index();

            // Clinical interpretation
            $table->enum('flag', [
                'normal',
                'low',
                'high',
                'critical',
                'abnormal',
                'pending'
            ])->default('pending')->index();

            // Reference ranges (snapshot at time of test)
            $table->decimal('reference_min', 12, 4)->nullable();
            $table->decimal('reference_max', 12, 4)->nullable();

            // Result metadata
            $table->text('interpretation')->nullable()->comment('Lab tech or system interpretation');
            $table->text('comments')->nullable();

            // Verification workflow
            $table->unsignedBigInteger('recorded_by_staff_id')->nullable();
            $table->unsignedBigInteger('verified_by_staff_id')->nullable();
            $table->timestamp('verified_at')->nullable();

            // Timing
            $table->timestamp('recorded_at')->index();
            $table->timestamp('updated_at_value')->nullable()->comment('When value was last changed');

            // Quality control
            $table->boolean('is_abnormal_flagged')->default(false)->index();
            $table->boolean('is_critical_alert_sent')->default(false);

            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->json('metadata')->nullable();

            // Foreign keys
            $table->foreign('lab_request_item_id')
                ->references('id')
                ->on('lab_request_items')
                ->onDelete('cascade');

            $table->foreign('template_field_id')
                ->references('id')
                ->on('lab_template_fields')
                ->onDelete('restrict');

            $table->foreign('recorded_by_staff_id')
                ->references('id')
                ->on('staff')
                ->onDelete('set null');

            $table->foreign('verified_by_staff_id')
                ->references('id')
                ->on('staff')
                ->onDelete('set null');

            $table->foreign('created_by_staff_id')
                ->references('id')
                ->on('staff')
                ->onDelete('set null');

            $table->foreign('updated_by_staff_id')
                ->references('id')
                ->on('staff')
                ->onDelete('set null');

            // Performance indexes
            $table->index(['lab_request_item_id', 'template_field_id']);
            $table->index(['flag', 'recorded_at']);
            $table->index(['verified_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_results');
    }
};