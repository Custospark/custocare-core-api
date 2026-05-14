<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_crew_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ambulance_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();

            $table->string('role', 30)->comment('driver, attendant, paramedic, emt, nurse, doctor, crew_lead');
            $table->boolean('is_primary_driver')->default(false);
            $table->date('certification_expiry')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ambulance_id', 'active']);
            $table->index('staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_crew_members');
    }
};
