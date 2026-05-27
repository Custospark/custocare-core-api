<?php

namespace Tests\Unit;

use App\Models\InventoryItem;
use App\Repositories\Contracts\InventoryItemRepositoryInterface;
use App\Services\InventoryItem\InventoryItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class InventoryItemServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $inventoryItemRepositoryMock;
    protected $inventoryItemService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->inventoryItemRepositoryMock = Mockery::mock(InventoryItemRepositoryInterface::class);
        $this->inventoryItemService = new InventoryItemService($this->inventoryItemRepositoryMock);
        
        // Suppress logging during tests
        Log::shouldReceive('error')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_gets_all_inventory_items_successfully()
    {
        $mockItems = InventoryItem::factory()->count(5)->make();
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('getAllPaginated')
            ->once()
            ->with([], 15, ['*'])
            ->andReturn($mockItems->paginate(15));
        
        $result = $this->inventoryItemService->getAllInventoryItems();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Inventory items retrieved successfully', $result['message']);
        $this->assertCount(5, $result['data']);
    }

    /** @test */
    public function it_handles_error_when_getting_inventory_items_fails()
    {
        $this->inventoryItemRepositoryMock
            ->shouldReceive('getAllPaginated')
            ->once()
            ->andThrow(new \Exception('Database error'));
        
        $result = $this->inventoryItemService->getAllInventoryItems();
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Failed to retrieve inventory items. Please try again later.', $result['message']);
        $this->assertEquals('INVENTORY_ITEMS_RETRIEVAL_FAILED', $result['error_code']);
    }

    /** @test */
    public function it_gets_inventory_item_by_uuid_successfully()
    {
        $mockItem = InventoryItem::factory()->make([
            'item_uuid' => '123e4567-e89b-12d3-a456-426614174000'
        ]);
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with('123e4567-e89b-12d3-a456-426614174000')
            ->andReturn($mockItem);
        
        $result = $this->inventoryItemService->getInventoryItemByUuid('123e4567-e89b-12d3-a456-426614174000');
        
        $this->assertTrue($result['success']);
        $this->assertEquals($mockItem, $result['data']);
    }

    /** @test */
    public function it_returns_not_found_when_inventory_item_does_not_exist()
    {
        $this->inventoryItemRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with('non-existent-uuid')
            ->andReturn(null);
        
        $result = $this->inventoryItemService->getInventoryItemByUuid('non-existent-uuid');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Inventory item not found', $result['message']);
        $this->assertEquals('INVENTORY_ITEM_NOT_FOUND', $result['error_code']);
    }

    /** @test */
    public function it_creates_inventory_item_successfully()
    {
        $itemData = [
            'item_code' => 'ITEM001',
            'item_name' => 'Test Item',
            'item_category' => 'medication',
            'unit_of_measure' => 'each',
            'package_quantity' => 1,
            'currency_code' => 'USD',
            'status' => 'active'
        ];
        
        $mockItem = InventoryItem::factory()->make($itemData);
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('itemCodeExists')
            ->once()
            ->with('ITEM001', null)
            ->andReturn(false);
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('create')
            ->once()
            ->with(array_merge($itemData, ['item_uuid' => Mockery::type('string')]))
            ->andReturn($mockItem);
        
        $result = $this->inventoryItemService->createInventoryItem($itemData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Inventory item created successfully', $result['message']);
        $this->assertEquals($mockItem, $result['data']);
    }

    /** @test */
    public function it_validates_duplicate_item_code_during_creation()
    {
        $itemData = [
            'item_code' => 'DUPLICATE001',
            'item_name' => 'Test Item',
            'item_category' => 'medication',
            'unit_of_measure' => 'each',
            'package_quantity' => 1,
            'currency_code' => 'USD',
            'status' => 'active'
        ];
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('itemCodeExists')
            ->once()
            ->with('DUPLICATE001', null)
            ->andReturn(true);
        
        $result = $this->inventoryItemService->createInventoryItem($itemData);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertArrayHasKey('item_code', $result['errors']);
    }

    /** @test */
    public function it_updates_inventory_item_successfully()
    {
        $existingItem = InventoryItem::factory()->create([
            'item_uuid' => '123e4567-e89b-12d3-a456-426614174000'
        ]);
        
        $updateData = [
            'item_name' => 'Updated Item Name',
            'status' => 'inactive'
        ];
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with('123e4567-e89b-12d3-a456-426614174000')
            ->andReturn($existingItem);
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('update')
            ->once()
            ->with($existingItem, $updateData)
            ->andReturn(true);
        
        $result = $this->inventoryItemService->updateInventoryItem(
            '123e4567-e89b-12d3-a456-426614174000',
            $updateData
        );
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Inventory item updated successfully', $result['message']);
    }

    /** @test */
    public function it_deletes_inventory_item_successfully()
    {
        $existingItem = InventoryItem::factory()->create([
            'item_uuid' => '123e4567-e89b-12d3-a456-426614174000'
        ]);
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('findByUuid')
            ->once()
            ->with('123e4567-e89b-12d3-a456-426614174000')
            ->andReturn($existingItem);
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with($existingItem)
            ->andReturn(true);
        
        $result = $this->inventoryItemService->deleteInventoryItem('123e4567-e89b-12d3-a456-426614174000');
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Inventory item deleted successfully', $result['message']);
    }

    /** @test */
    public function it_searches_inventory_items_successfully()
    {
        $searchTerm = 'aspirin';
        $mockItems = InventoryItem::factory()->count(3)->make();
        
        $this->inventoryItemRepositoryMock
            ->shouldReceive('search')
            ->once()
            ->with($searchTerm, [], 15)
            ->andReturn($mockItems->paginate(15));
        
        $result = $this->inventoryItemService->searchInventoryItems($searchTerm);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Search completed successfully', $result['message']);
    }

    /** @test */
    public function it_returns_error_when_search_term_is_empty()
    {
        $result = $this->inventoryItemService->searchInventoryItems('');
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Search term is required', $result['message']);
        $this->assertEquals('SEARCH_TERM_REQUIRED', $result['error_code']);
    }
}