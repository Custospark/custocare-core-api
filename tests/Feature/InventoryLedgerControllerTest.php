<?php

namespace Tests\Feature;

use App\Models\InventoryLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature tests for InventoryLedgerController.
 * Tests API endpoints and their responses.
 */
class InventoryLedgerControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * The authenticated user.
     *
     * @var User
     */
    protected User $user;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);
        
        // Assign permissions to user (assuming Spatie Laravel Permission package)
        $this->user->givePermissionTo([
            'inventory_ledger.view',
            'inventory_ledger.create',
            'inventory_ledger.update',
            'inventory_ledger.delete',
            'inventory_ledger.verify',
            'inventory.balance.view',
        ]);
        
        // Authenticate the user
        $this->actingAs($this->user, 'sanctum');
    }

    /**
     * Test retrieving all inventory ledger entries.
     *
     * @return void
     */
    public function test_index_returns_ledger_entries(): void
    {
        // Arrange
        InventoryLedger::factory()->count(5)->create();
        
        // Act
        $response = $this->getJson('/api/v1/inventory/ledger');
        
        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'transaction_uuid',
                        'facility_id',
                        'inventory_item_id',
                        'transaction_type',
                        'quantity_change',
                        'balance_after_transaction',
                        'unit_of_measure',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /**
     * Test retrieving inventory ledger entries with filters.
     *
     * @return void
     */
    public function test_index_with_filters(): void
    {
        // Arrange
        $facilityId = 1;
        $transactionType = 'purchase';
        
        InventoryLedger::factory()->create([
            'facility_id' => $facilityId,
            'transaction_type' => $transactionType,
        ]);
        
        InventoryLedger::factory()->create([
            'facility_id' => 2,
            'transaction_type' => 'consumption_visit',
        ]);
        
        // Act
        $response = $this->getJson("/api/v1/inventory/ledger?facility_id={$facilityId}&transaction_type={$transactionType}");
        
        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'facility_id' => $facilityId,
                'transaction_type' => $transactionType,
            ]);
    }

    /**
     * Test creating a new inventory ledger entry.
     *
     * @return void
     */
    public function test_store_creates_ledger_entry(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'transaction_type' => 'purchase',
            'quantity_change' => 25.5,
            'balance_after_transaction' => 25.5,
            'unit_of_measure' => 'units',
            'transaction_cause' => 'manual_entry',
            'performed_by_staff_id' => 1,
            'transaction_timestamp' => now()->toDateTimeString(),
            'transaction_notes' => 'Test purchase entry',
        ];
        
        // Act
        $response = $this->postJson('/api/v1/inventory/ledger', $data);
        
        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'transaction_uuid',
                    'facility_id',
                    'inventory_item_id',
                    'transaction_type',
                    'quantity_change',
                    'balance_after_transaction',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Inventory ledger entry created successfully.',
                'data' => [
                    'facility_id' => $data['facility_id'],
                    'inventory_item_id' => $data['inventory_item_id'],
                    'transaction_type' => $data['transaction_type'],
                    'quantity_change' => (float) $data['quantity_change'],
                ],
            ]);
        
        $this->assertDatabaseHas('inventory_ledger', [
            'facility_id' => $data['facility_id'],
            'inventory_item_id' => $data['inventory_item_id'],
            'transaction_type' => $data['transaction_type'],
        ]);
    }

    /**
     * Test store validation errors.
     *
     * @return void
     */
    public function test_store_validation_errors(): void
    {
        // Arrange
        $invalidData = [
            'facility_id' => 'not_an_integer',
            'transaction_type' => 'invalid_type',
            // Missing required fields
        ];
        
        // Act
        $response = $this->postJson('/api/v1/inventory/ledger', $invalidData);
        
        // Assert
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ])
            ->assertJson([
                'success' => false,
                'message' => 'Validation errors occurred.',
            ]);
    }

    /**
     * Test retrieving a specific ledger entry.
     *
     * @return void
     */
    public function test_show_returns_ledger_entry(): void
    {
        // Arrange
        $ledgerEntry = InventoryLedger::factory()->create();
        
        // Act
        $response = $this->getJson("/api/v1/inventory/ledger/{$ledgerEntry->id}");
        
        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'transaction_uuid',
                    'facility_id',
                    'inventory_item_id',
                    'transaction_type',
                    'quantity_change',
                    'balance_after_transaction',
                    'unit_of_measure',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $ledgerEntry->id,
                    'transaction_uuid' => $ledgerEntry->transaction_uuid,
                    'facility_id' => $ledgerEntry->facility_id,
                ],
            ]);
    }

    /**
     * Test show returns 404 for non-existent entry.
     *
     * @return void
     */
    public function test_show_returns_404_for_non_existent_entry(): void
    {
        // Act
        $response = $this->getJson('/api/v1/inventory/ledger/999999');
        
        // Assert
        $response->assertStatus(404);
    }

    /**
     * Test updating a ledger entry.
     *
     * @return void
     */
    public function test_update_modifies_ledger_entry(): void
    {
        // Arrange
        $ledgerEntry = InventoryLedger::factory()->create([
            'verified_at' => null, // Can only update unverified entries
        ]);
        
        $updateData = [
            'transaction_notes' => 'Updated notes for correction',
            'reference_document_number' => 'CORR-001',
        ];
        
        // Act
        $response = $this->putJson("/api/v1/inventory/ledger/{$ledgerEntry->id}", $updateData);
        
        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory ledger entry updated successfully.',
                'data' => [
                    'transaction_notes' => $updateData['transaction_notes'],
                    'reference_document_number' => $updateData['reference_document_number'],
                ],
            ]);
        
        $this->assertDatabaseHas('inventory_ledger', [
            'id' => $ledgerEntry->id,
            'transaction_notes' => $updateData['transaction_notes'],
        ]);
    }

    /**
     * Test updating a verified ledger entry fails.
     *
     * @return void
     */
    public function test_update_verified_entry_fails(): void
    {
        // Arrange
        $ledgerEntry = InventoryLedger::factory()->create([
            'verified_at' => now()->subDay(),
        ]);
        
        $updateData = [
            'transaction_notes' => 'Attempt to update verified entry',
        ];
        
        // Act
        $response = $this->putJson("/api/v1/inventory/ledger/{$ledgerEntry->id}", $updateData);
        
        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test deleting a ledger entry.
     *
     * @return void
     */
    public function test_destroy_deletes_ledger_entry(): void
    {
        // Arrange
        $ledgerEntry = InventoryLedger::factory()->create([
            'verified_at' => null,
        ]);
        
        // Ensure it's not the latest entry
        InventoryLedger::factory()->create([
            'facility_id' => $ledgerEntry->facility_id,
            'inventory_item_id' => $ledgerEntry->inventory_item_id,
            'transaction_timestamp' => now()->addHour(),
        ]);
        
        // Act
        $response = $this->deleteJson("/api/v1/inventory/ledger/{$ledgerEntry->id}");
        
        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory ledger entry deleted successfully.',
            ]);
        
        $this->assertDatabaseMissing('inventory_ledger', [
            'id' => $ledgerEntry->id,
        ]);
    }

    /**
     * Test verifying a ledger entry.
     *
     * @return void
     */
    public function test_verify_ledger_entry(): void
    {
        // Arrange
        $ledgerEntry = InventoryLedger::factory()->create([
            'verified_at' => null,
        ]);
        
        $verifyData = [
            'verified_by_staff_id' => 2,
            'notes' => 'Verified during routine audit',
        ];
        
        // Act
        $response = $this->postJson("/api/v1/inventory/ledger/{$ledgerEntry->id}/verify", $verifyData);
        
        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory ledger entry verified successfully.',
                'data' => [
                    'verified_by_staff_id' => $verifyData['verified_by_staff_id'],
                    'is_verified' => true,
                ],
            ]);
        
        $this->assertDatabaseHas('inventory_ledger', [
            'id' => $ledgerEntry->id,
            'verified_by_staff_id' => $verifyData['verified_by_staff_id'],
            'verified_at' => now(),
        ]);
    }

    /**
     * Test getting current balance.
     *
     * @return void
     */
    public function test_current_balance(): void
    {
        // Arrange
        $facilityId = 1;
        $inventoryItemId = 1;
        
        // Create some ledger entries
        InventoryLedger::factory()->create([
            'facility_id' => $facilityId,
            'inventory_item_id' => $inventoryItemId,
            'quantity_change' => 10.0,
            'balance_after_transaction' => 10.0,
            'transaction_timestamp' => now()->subDays(2),
        ]);
        
        InventoryLedger::factory()->create([
            'facility_id' => $facilityId,
            'inventory_item_id' => $inventoryItemId,
            'quantity_change' => -3.0,
            'balance_after_transaction' => 7.0,
            'transaction_timestamp' => now()->subDays(1),
        ]);
        
        InventoryLedger::factory()->create([
            'facility_id' => $facilityId,
            'inventory_item_id' => $inventoryItemId,
            'quantity_change' => 5.0,
            'balance_after_transaction' => 12.0, // Current balance
            'transaction_timestamp' => now(),
        ]);
        
        // Act
        $response = $this->getJson("/api/v1/inventory/ledger/balance/current?facility_id={$facilityId}&inventory_item_id={$inventoryItemId}");
        
        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'facility_id' => $facilityId,
                    'inventory_item_id' => $inventoryItemId,
                    'current_balance' => 12.0,
                ],
            ]);
    }

    /**
     * Test recording a purchase through specialized endpoint.
     *
     * @return void
     */
    public function test_record_purchase_endpoint(): void
    {
        // Arrange
        $purchaseData = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'quantity' => 100.0,
            'unit_of_measure' => 'boxes',
            'unit_cost_at_transaction' => 25.99,
            'performed_by_staff_id' => 1,
            'lot_number' => 'LOT-2024-001',
            'expiry_date' => now()->addYears(2)->toDateString(),
            'transaction_notes' => 'Bulk purchase for Q1 2024',
        ];
        
        // Act
        $response = $this->postJson('/api/v1/inventory/ledger/purchase', $purchaseData);
        
        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Purchase recorded successfully.',
                'data' => [
                    'transaction_type' => 'purchase',
                    'quantity_change' => 100.0,
                    'lot_number' => 'LOT-2024-001',
                ],
            ]);
        
        $this->assertDatabaseHas('inventory_ledger', [
            'transaction_type' => 'purchase',
            'quantity_change' => 100.0,
            'lot_number' => 'LOT-2024-001',
        ]);
    }

    /**
     * Test unauthorized access.
     *
     * @return void
     */
    public function test_unauthorized_access(): void
    {
        // Arrange - Create user without permissions
        $unauthorizedUser = User::factory()->create();
        // $this->actingAs($unauthorizedUser, 'sanctum');
        
        // Act
        $response = $this->getJson('api/inventory/ledger');
        
        // Assert - Should be 403 or redirect based on your setup
        $response->assertStatus(403);
    }

    /**
     * Test pagination.
     *
     * @return void
     */
    public function test_pagination(): void
    {
        // Arrange
        InventoryLedger::factory()->count(35)->create();
        
        // Act
        $response = $this->getJson('/api/v1/inventory/ledger?per_page=10');
        
        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }
}
