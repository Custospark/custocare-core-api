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
    {
       Schema::create('user_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('title')->nullable();
        $table->string('display_name')->nullable(); // optional
        
        $table->date('dob')->nullable();
        $table->enum('gender', ['male', 'female', 'other'])->nullable();
        
        $table->string('address_line1')->nullable();
        $table->string('address_line2')->nullable();
        $table->string('city')->nullable();
        $table->string('state')->nullable();
        $table->string('country')->nullable();
        $table->string('postal_code')->nullable();
        
        $table->json('metadata')->nullable(); // flexible extension
        
        $table->timestamps();
        $table->softDeletes();
    });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profile');
    }
};
