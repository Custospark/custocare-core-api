<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data Subject Access Request register (Act Sec 16/24-28, Guidelines
     * Sec 5.3/7/9). Every request is logged regardless of outcome; the row
     * carries its own SLA clock (5 working days) and the downstream
     * notification trail an inspector asks for. Forward-only, additive.
     */
    public function up(): void
    {
        Schema::create('dsar_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_uuid')->unique()->index();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('requested_by_staff_id')->nullable()
                ->comment('Staff logging an in-person request on the subject\'s behalf');
            $table->enum('request_type', [
                'access',
                'correction',
                'erasure',
                'restriction',
                'objection',
            ])->index();
            $table->enum('channel', ['in_person', 'email', 'phone', 'portal'])->default('in_person');
            $table->text('details')->nullable()
                ->comment('What is requested: elements, justification, lawful basis cited');
            $table->enum('status', ['received', 'in_review', 'fulfilled', 'rejected'])
                ->default('received')->index();
            $table->timestamp('sla_due_at')->index()
                ->comment('5 working days from receipt');
            $table->text('resolution_notes')->nullable();
            $table->text('rejection_reasons')->nullable();
            $table->boolean('downstream_notified')->default(false)
                ->comment('All disclosed-to entities informed of correction/action');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsar_requests');
    }
};
