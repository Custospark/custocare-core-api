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
    {     /**
         * USERS - Root identity table (Global Identity Anchor)
         * Shard Strategy: Hash(national_id_hash) for global distribution
         * Security: Encrypted at rest (national_id, contact_info)
         */
         Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('global_user_uuid')->unique()->index();
            $table->string('national_id_hash', 128)->unique()->comment('SHA-256 hashed national ID for privacy');
            $table->string('national_id_encrypted', 512)->comment('AES-256 encrypted national ID');
            $table->string('national_id_country_code', 3)->index();
            
            // Identity verification
            $table->enum('identity_state', ['pending', 'verified', 'suspended', 'archived'])->default('pending')->index();
            $table->timestamp('identity_verified_at')->nullable();
            $table->string('identity_verification_method', 50)->nullable()->comment('passport, biometric, government_id, etc.');
            $table->unsignedBigInteger('identity_verified_by_staff_id')->nullable();
            
            // Data residency & compliance
            $table->string('data_residency_region', 10)->index()->comment('EU, US, APAC, etc.');
            $table->json('allowed_processing_regions')->nullable()->comment('Regions where data can be processed');
            $table->unsignedBigInteger('created_from_facility_id')->nullable()->comment('First touchpoint facility');
            
            // Contact information (encrypted)
            $table->string('email_encrypted', 512)->nullable();
            $table->string('email_hash', 128)->nullable()->index();
            $table->string('phone_encrypted', 512)->nullable();
            $table->string('phone_hash', 128)->nullable()->index();
            
            // Account management
            $table->string('password_hash', 255)->nullable()->comment('For patient portal access');
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('requires_password_change')->default(false);
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_secret_encrypted', 512)->nullable();
            
            // Session & security tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->string('last_login_user_agent', 512)->nullable();
            $table->unsignedInteger('failed_login_attempts')->default(0);
            $table->timestamp('account_locked_until')->nullable();
            
            // Audit metadata
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->unsignedBigInteger('updated_by_staff_id')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->json('metadata')->nullable()->comment('Flexible extension point');
            
            // Performance indexes
            $table->index(['identity_state', 'data_residency_region']);
            $table->index(['created_at', 'identity_state']);
            $table->index('created_from_facility_id');
        });
                   
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
