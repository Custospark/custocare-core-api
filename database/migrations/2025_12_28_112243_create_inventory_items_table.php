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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('item_uuid')->unique()->index();
            $table->unsignedBigInteger('facility_id')->unique()->index();

            
            // Item identification
            $table->string('item_code', 100)->unique()->index();
            $table->string('item_name', 300);
            $table->text('item_description')->nullable();
            
            // Classification
            $table->enum('item_category', [
                'medication',
                'medical_supply',
                'surgical_instrument',
                'diagnostic_equipment',
                'implantable_device',
                'prosthetic',
                'laboratory_reagent',
                'personal_protective_equipment',
                'administrative_supply',
                'other'
            ])->index();
            
            $table->string('item_subcategory', 100)->nullable();
            
            // Medication-specific fields
            $table->string('generic_name', 300)->nullable();
            $table->string('brand_name', 300)->nullable();
            $table->string('ndc_code', 20)->nullable()->index()->comment('National Drug Code');
            $table->string('drug_class', 100)->nullable();
            $table->enum('controlled_substance_schedule', ['I', 'II', 'III', 'IV', 'V', 'non_controlled'])->nullable();
            $table->json('active_ingredients')->nullable();
            $table->string('dosage_form', 100)->nullable();
            $table->string('strength', 100)->nullable();
            $table->string('route_of_administration', 100)->nullable();
            
            // Manufacturer information
            $table->string('manufacturer', 200)->nullable();
            $table->string('manufacturer_item_number', 100)->nullable();
            $table->string('supplier', 200)->nullable();
            
            // Unit information
            $table->string('unit_of_measure', 50)->default('each');
            $table->unsignedSmallInteger('package_quantity')->default(1);
            $table->string('packaging_type', 100)->nullable();
            
            // Pricing
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('average_wholesale_price', 10, 2)->nullable();
            $table->string('currency_code', 3)->default('USD');
            
            // Storage & handling
            $table->json('storage_requirements')->nullable()->comment('Temperature, humidity, light');
            $table->boolean('requires_refrigeration')->default(false);
            $table->boolean('requires_controlled_access')->default(false);
            $table->string('storage_location_type', 100)->nullable();
            
            // Regulatory
            $table->boolean('requires_prescription')->default(false);
            $table->json('regulatory_approvals')->nullable();
            $table->string('fda_approval_number', 100)->nullable();
            
            // Safety information
            $table->boolean('is_hazardous')->default(false);
            $table->json('safety_warnings')->nullable();
            $table->json('contraindications')->nullable();
            $table->text('special_handling_instructions')->nullable();
            
            // Inventory management
            $table->boolean('is_billable')->default(true);
            $table->boolean('track_by_lot')->default(false);
            $table->boolean('track_by_serial')->default(false);
            $table->unsignedSmallInteger('reorder_point')->nullable();
            $table->unsignedSmallInteger('reorder_quantity')->nullable();
            $table->unsignedSmallInteger('safety_stock_level')->nullable();
            $table->unsignedSmallInteger('max_stock_level')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'discontinued', 'recalled'])->default('active')->index();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->json('metadata')->nullable();
            
            // Performance indexes
            $table->index(['item_category', 'status']);
            $table->index(['controlled_substance_schedule', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};