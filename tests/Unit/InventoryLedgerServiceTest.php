<?php

namespace Tests\Unit;

use App\Models\InventoryLedger;
use App\Repositories\Contracts\InventoryLedgerRepositoryInterface;
use App\Services\InventoryLedger\InventoryLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for InventoryLedgerService.
 * Focuses on business logic and transaction handling.
 */
class InventoryLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The service instance.
     *
     * @var InventoryLedgerService
     */
    protected InventoryLedgerService $service;

    /**
     * The repository mock.
     *
     * @var Mockery\MockInterface|InventoryLedgerRepositoryInterface
     */
    protected $repositoryMock;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repositoryMock = Mockery::mock(InventoryLedgerRepositoryInterface::class);
        $this->service = new InventoryLedgerService($this->repositoryMock);
    }

    /**
     * Clean up the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test creating a ledger entry successfully.
     *
     * @return void
     */
    public function test_create_ledger_entry_successfully(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'transaction_type' => 'purchase',
            'quantity_change' => 10.5,
            'unit_of_measure' => 'units',
            'transaction_cause' => 'manual_entry',
            'performed_by_staff_id' => 1,
            'transaction_timestamp' => now(),
        ];
        
        $expectedBalance = 10.5;
        
        $ledgerEntry = InventoryLedger::factory()->make(array_merge($data, [
            'balance_after_transaction' => $expectedBalance,
        ]));
        
        $this->repositoryMock->shouldReceive('getCurrentBalance')
            ->with(1, 1)
            ->andReturn(0.0);
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['facility_id'] === $data['facility_id']
                    && $arg['inventory_item_id'] === $data['inventory_item_id']
                    && $arg['quantity_change'] === $data['quantity_change'];
            }))
            ->andReturn($ledgerEntry);
        
        // Act
        $result = $this->service->createLedgerEntry($data);
        
        // Assert
        $this->assertInstanceOf(InventoryLedger::class, $result);
        $this->assertEquals($data['facility_id'], $result->facility_id);
        $this->assertEquals($expectedBalance, $result->balance_after_transaction);
    }

    /**
     * Test creating a ledger entry with insufficient inventory.
     *
     * @return void
     */
    public function test_create_ledger_entry_with_insufficient_inventory(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'transaction_type' => 'consumption_visit',
            'quantity_change' => -15.0, // Trying to consume 15 units
            'unit_of_measure' => 'units',
            'transaction_cause' => 'patient_use',
            'performed_by_staff_id' => 1,
            'transaction_timestamp' => now(),
        ];
        
        $this->repositoryMock->shouldReceive('getCurrentBalance')
            ->with(1, 1)
            ->andReturn(10.0); // Only 10 units available
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient inventory');
        
        // Act
        $this->service->createLedgerEntry($data);
    }

    /**
     * Test creating a ledger entry with negative balance allowed.
     *
     * @return void
     */
    public function test_create_ledger_entry_with_negative_balance_allowed(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'transaction_type' => 'adjustment_decrease',
            'quantity_change' => -5.0,
            'unit_of_measure' => 'units',
            'transaction_cause' => 'reconciliation',
            'performed_by_staff_id' => 1,
            'transaction_timestamp' => now(),
        ];
        
        $expectedBalance = -5.0; // Negative balance allowed for adjustments
        
        $ledgerEntry = InventoryLedger::factory()->make(array_merge($data, [
            'balance_after_transaction' => $expectedBalance,
        ]));
        
        $this->repositoryMock->shouldReceive('getCurrentBalance')
            ->with(1, 1)
            ->andReturn(0.0);
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($data) {
                return $arg['transaction_type'] === 'adjustment_decrease';
            }))
            ->andReturn($ledgerEntry);
        
        // Act
        $result = $this->service->createLedgerEntry($data);
        
        // Assert
        $this->assertInstanceOf(InventoryLedger::class, $result);
        $this->assertEquals($expectedBalance, $result->balance_after_transaction);
    }

    /**
     * Test getting current balance.
     *
     * @return void
     */
    public function test_get_current_balance(): void
    {
        // Arrange
        $facilityId = 1;
        $inventoryItemId = 1;
        $expectedBalance = 25.5;
        
        $this->repositoryMock->shouldReceive('getCurrentBalance')
            ->with($facilityId, $inventoryItemId)
            ->andReturn($expectedBalance);
        
        // Act
        $result = $this->service->getCurrentBalance($facilityId, $inventoryItemId);
        
        // Assert
        $this->assertEquals($expectedBalance, $result);
    }

    /**
     * Test verifying a ledger entry.
     *
     * @return void
     */
    public function test_verify_ledger_entry(): void
    {
        // Arrange
        $ledgerEntryId = 1;
        $verifiedByStaffId = 2;
        $notes = 'Verified during audit';
        
        $ledgerEntry = InventoryLedger::factory()->make([
            'id' => $ledgerEntryId,
            'verified_at' => null,
        ]);
        
        $verifiedEntry = InventoryLedger::factory()->make([
            'id' => $ledgerEntryId,
            'verified_by_staff_id' => $verifiedByStaffId,
            'verified_at' => now(),
            'transaction_notes' => "Verification: " . $notes,
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with($ledgerEntryId, [])
            ->andReturn($ledgerEntry);
        
        $this->repositoryMock->shouldReceive('update')
            ->with($ledgerEntryId, Mockery::on(function ($arg) use ($verifiedByStaffId) {
                return $arg['verified_by_staff_id'] === $verifiedByStaffId
                    && !is_null($arg['verified_at']);
            }))
            ->andReturn($verifiedEntry);
        
        // Act
        $result = $this->service->verifyLedgerEntry($ledgerEntryId, $verifiedByStaffId, $notes);
        
        // Assert
        $this->assertInstanceOf(InventoryLedger::class, $result);
        $this->assertEquals($verifiedByStaffId, $result->verified_by_staff_id);
        $this->assertNotNull($result->verified_at);
    }

    /**
     * Test verifying an already verified ledger entry.
     *
     * @return void
     */
    public function test_verify_already_verified_ledger_entry(): void
    {
        // Arrange
        $ledgerEntryId = 1;
        $verifiedByStaffId = 2;
        
        $ledgerEntry = InventoryLedger::factory()->make([
            'id' => $ledgerEntryId,
            'verified_at' => now()->subDay(),
        ]);
        
        $this->repositoryMock->shouldReceive('findById')
            ->with($ledgerEntryId, [])
            ->andReturn($ledgerEntry);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already been verified');
        
        // Act
        $this->service->verifyLedgerEntry($ledgerEntryId, $verifiedByStaffId);
    }

    /**
     * Test recording a purchase transaction.
     *
     * @return void
     */
    public function test_record_purchase(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'quantity' => 50.0,
            'unit_of_measure' => 'boxes',
            'unit_cost_at_transaction' => 10.99,
            'performed_by_staff_id' => 1,
            'transaction_cause' => 'manual_entry',
        ];
        
        $expectedData = array_merge($data, [
            'transaction_type' => 'purchase',
            'quantity_change' => 50.0,
            'total_cost' => 549.5, // 50 * 10.99
        ]);
        
        $ledgerEntry = InventoryLedger::factory()->make($expectedData);
        
        $this->repositoryMock->shouldReceive('getCurrentBalance')
            ->with(1, 1)
            ->andReturn(100.0);
        
        $this->repositoryMock->shouldReceive('create')
            ->with(Mockery::on(function ($arg) use ($expectedData) {
                return $arg['transaction_type'] === 'purchase'
                    && $arg['quantity_change'] === 50.0;
            }))
            ->andReturn($ledgerEntry);
        
        // Act
        $result = $this->service->recordPurchase($expectedData);
        
        // Assert
        $this->assertInstanceOf(InventoryLedger::class, $result);
        $this->assertEquals('purchase', $result->transaction_type);
    }

    /**
     * Test recording a purchase with negative quantity.
     *
     * @return void
     */
    public function test_record_purchase_with_negative_quantity(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'quantity' => -10.0, // Negative quantity for purchase
            'unit_of_measure' => 'units',
            'performed_by_staff_id' => 1,
        ];
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Purchase quantity must be positive');
        
        // Act
        $this->service->recordPurchase($data);
    }

    /**
     * Test generating transaction hash.
     *
     * @return void
     */
    public function test_generate_transaction_hash(): void
    {
        // Arrange
        $data = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'transaction_type' => 'purchase',
            'quantity_change' => 10.0,
            'transaction_timestamp' => '2024-01-01 10:00:00',
        ];
        
        // Act
        $hash1 = $this->service->generateTransactionHash($data);
        
        // Same data should produce same hash
        $hash2 = $this->service->generateTransactionHash($data);
        
        // Different data should produce different hash
        $differentData = $data;
        $differentData['quantity_change'] = 20.0;
        $hash3 = $this->service->generateTransactionHash($differentData);
        
        // Assert
        $this->assertEquals($hash1, $hash2);
        $this->assertNotEquals($hash1, $hash3);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 produces 64-character hex string
    }

    /**
     * Test transaction validation.
     *
     * @return void
     */
    public function test_validate_transaction(): void
    {
        // Arrange
        $validData = [
            'facility_id' => 1,
            'inventory_item_id' => 1,
            'transaction_type' => 'purchase',
            'quantity_change' => 10.0,
            'unit_of_measure' => 'units',
            'transaction_cause' => 'manual_entry',
            'performed_by_staff_id' => 1,
        ];
        
        // Act
        $result = $this->service->validateTransaction($validData);
        
        // Assert
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test transaction validation with errors.
     *
     * @return void
     */
    public function test_validate_transaction_with_errors(): void
    {
        // Arrange
        $invalidData = [
            'facility_id' => null, // Missing required field
            'transaction_type' => 'invalid_type', // Invalid type
            'quantity_change' => 'not_a_number', // Not numeric
        ];
        
        // Act
        $result = $this->service->validateTransaction($invalidData);
        
        // Assert
        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('facility_id', $result['errors']);
        $this->assertArrayHasKey('transaction_type', $result['errors']);
        $this->assertArrayHasKey('quantity_change', $result['errors']);
    }

    /**
     * Test updating a ledger entry.
     *
     * @return void
     */
    public function test_update_ledger_entry(): void
    {
        // Arrange
        $ledgerEntryId = 1;
        $updateData = [
            'transaction_notes' => 'Updated notes',
            'reference_document_number' => 'DOC-12345',
        ];
        
        $existingEntry = InventoryLedger::factory()->make([
            'id' => $ledgerEntryId,
            'verified_at' => null,
        ]);
        
        $updatedEntry = InventoryLedger::factory()->make(array_merge(
            $existingEntry->toArray(),
            $updateData
        ));
        
        $this->repositoryMock->shouldReceive('findById')
            ->with($ledgerEntryId, [])
            ->andReturn($existingEntry);
        
        $this->repositoryMock->shouldReceive('update')
            ->with($ledgerEntryId, Mockery::on(function ($arg) use ($updateData) {
                return $arg['transaction_notes'] === $updateData['transaction_notes'];
            }))
            ->andReturn($updatedEntry);
        
        // Act
        $result = $this->service->updateLedgerEntry($ledgerEntryId, $updateData);
        
        // Assert
        $this->assertInstanceOf(InventoryLedger::class, $result);
        $this->assertEquals($updateData['transaction_notes'], $result->transaction_notes);
    }
}
