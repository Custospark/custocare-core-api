<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * AUDIT_LOGS - Immutable compliance audit trail
     * Shard Strategy: (entity_type, DATE(created_at))
     * Retention: 7 years minimum (HIPAA requirement)
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('audit_uuid')->unique()->index();
            
            // Operation details
            $table->enum('operation', [
                'create',
                'read',
                'update',
                'delete',
                'access',
                'export',
                'print',
                'share',
                'consent_change',
                'authentication',
                'authorization_failure'
            ])->index();
            
            // Entity information
            $table->string('entity_type', 100)->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('entity_identifier', 200)->nullable();
            
            // Change tracking
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            
            // Actor information
            $table->enum('performed_by_type', ['staff', 'patient', 'system', 'external_api', 'scheduled_job'])->index();
            $table->unsignedBigInteger('performed_by_id')->nullable()->index();
            $table->string('performed_by_identifier', 200)->nullable();
            $table->string('performed_by_role', 100)->nullable();
            
            // Request context
            $table->string('request_id', 100)->index()->comment('Correlation ID for distributed tracing');
            $table->string('session_id', 100)->nullable();
            $table->string('user_ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('geolocation', 100)->nullable();
            
            // Compliance & legal
            $table->enum('compliance_reason', [
                'treatment',
                'payment',
                'healthcare_operations',
                'billing',
                'audit',
                'research',
                'legal_request',
                'patient_request',
                'emergency_access',
                'break_glass'
            ])->index();
            
            $table->boolean('legal_hold_flag')->default(false)->index();
            $table->text('justification')->nullable();
            
            // Facility & department context
            $table->unsignedBigInteger('facility_id')->nullable()->index();
            $table->unsignedBigInteger('department_id')->nullable();
            
            // Patient privacy tracking (for HIPAA accounting of disclosures)
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->boolean('phi_accessed')->default(false)->index()->comment('Protected Health Information');
            $table->json('phi_fields_accessed')->nullable();
            
            // Outcome
            $table->enum('result', ['success', 'failure', 'partial', 'denied'])->index();
            $table->text('failure_reason')->nullable();
            $table->string('error_code', 50)->nullable();
            
            // Performance
            $table->unsignedSmallInteger('operation_duration_ms')->nullable();
            
            // Timestamp (immutable)
            $table->timestamp('created_at')->index();
            
            // Metadata
            $table->json('metadata')->nullable();
            
            // Composite indexes for common compliance queries
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['performed_by_type', 'performed_by_id', 'created_at']);
            $table->index(['patient_id', 'phi_accessed', 'created_at']); // HIPAA accounting
            $table->index(['compliance_reason', 'created_at']);
            $table->index(['facility_id', 'created_at']);
            $table->index(['legal_hold_flag', 'created_at']);
            
            // Note: No updated_at column as audit logs are immutable
            $table->timestamp('archived_at')->nullable()->comment('When log was moved to cold storage');
            $table->timestamp('purged_at')->nullable()->comment('When log was purged after retention period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};