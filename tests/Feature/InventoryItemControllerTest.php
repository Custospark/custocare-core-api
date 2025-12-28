<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryItemControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $inventoryItem;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        
        $this->inventoryItem = InventoryItem::factory()->create([
            'created_by_staff_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_list_inventory_items()
    {
        InventoryItem::factory()->count(10)->create();
        
        $response = $this->getJson('/api/inventory-items');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'item_uuid',
                        'item_code',
                        'item_name',
                        'item_category',
                        'status',
                        'links'
                    ]
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total'
                ]
            ]);
    }

    /** @test */
    public function it_can_create_an_inventory_item()
    {
        $itemData = [
            'item_code' => 'NEWITEM001',
            'item_name' => 'New Test Item',
            'item_description' => 'Test description',
            'item_category' => 'medication',
            'unit_of_measure' => 'each',
            'package_quantity' => 10,
            'currency_code' => 'USD',
            'status' => 'active'
        ];
        
        $response = $this->postJson('/api/inventory-items', $itemData);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory item created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'item_uuid',
                    'item_code',
                    'item_name'
                ]
            ]);
        
        $this->assertDatabaseHas('inventory_items', [
            'item_code' => 'NEWITEM001'
        ]);
    }

    /** @test */
    public function it_validates_required_fields_when_creating_inventory_item()
    {
        $response = $this->postJson('/api/inventory-items', []);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'VALIDATION_FAILED'
            ])
            ->assertJsonValidationErrors([
                'item_code',
                'item_name',
                'item_category',
                'unit_of_measure',
                'package_quantity',
                'currency_code',
                'status'
            ]);
    }

    /** @test */
    public function it_can_show_an_inventory_item()
    {
        $response = $this->getJson("/api/inventory-items/{$this->inventoryItem->item_uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'item_uuid' => $this->inventoryItem->item_uuid,
                    'item_code' => $this->inventoryItem->item_code
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_when_inventory_item_not_found()
    {
        $response = $this->getJson('/api/inventory-items/non-existent-uuid');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Inventory item not found'
            ]);
    }

    /** @test */
    public function it_can_update_an_inventory_item()
    {
        $updateData = [
            'item_name' => 'Updated Item Name',
            'item_description' => 'Updated description',
            'status' => 'inactive'
        ];
        
        $response = $this->putJson("/api/inventory-items/{$this->inventoryItem->item_uuid}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory item updated successfully'
            ]);
        
        $this->assertDatabaseHas('inventory_items', [
            'item_uuid' => $this->inventoryItem->item_uuid,
            'item_name' => 'Updated Item Name',
            'status' => 'inactive'
        ]);
    }

    /** @test */
    public function it_can_delete_an_inventory_item()
    {
        $inventoryItem = InventoryItem::factory()->create(['status' => 'inactive']);
        
        $response = $this->deleteJson("/api/inventory-items/{$inventoryItem->item_uuid}");
        
        $response->assertStatus(204);
        
        $this->assertSoftDeleted('inventory_items', [
            'id' => $inventoryItem->id
        ]);
    }

    /** @test */
    public function it_can_restore_a_deleted_inventory_item()
    {
        $inventoryItem = InventoryItem::factory()->create(['status' => 'inactive']);
        $inventoryItem->delete();
        
        $response = $this->postJson("/api/inventory-items/{$inventoryItem->item_uuid}/restore");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Inventory item restored successfully'
            ]);
        
        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'deleted_at' => null
        ]);
    }

    /** @test */
    public function it_can_search_inventory_items()
    {
        InventoryItem::factory()->create([
            'item_name' => 'Special Aspirin Tablets',
            'generic_name' => 'Acetylsalicylic Acid'
        ]);
        
        $response = $this->getJson('/api/inventory-items/search?q=aspirin');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Search completed successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'item_name',
                        'generic_name'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_error_when_search_term_is_empty()
    {
        $response = $this->getJson('/api/inventory-items/search?q=');
        
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Search term is required'
            ]);
    }

    /** @test */
    public function it_can_get_inventory_items_by_category()
    {
        InventoryItem::factory()->count(3)->create([
            'item_category' => 'medication'
        ]);
        
        $response = $this->getJson('/api/inventory-items/category/medication');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonCount(4, 'data'); // 3 created + 1 from setUp
    }

    /** @test */
    public function it_can_get_controlled_substances()
    {
        InventoryItem::factory()->create([
            'item_category' => 'medication',
            'controlled_substance_schedule' => 'II'
        ]);
        
        $response = $this->getJson('/api/inventory-items/controlled-substances');
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Controlled substances retrieved successfully'
            ]);
    }
}