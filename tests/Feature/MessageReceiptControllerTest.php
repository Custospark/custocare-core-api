<?php

namespace Tests\Feature;

use App\Models\MessageReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageReceiptControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * @var User
     */
    protected User $user;

    /**
     * @var User
     */
    protected User $adminUser;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        
        // Create test message receipts
        MessageReceipt::factory()->count(10)->create();
    }

    /**
     * Test retrieving all message receipts as admin.
     *
     * @return void
     */
    public function test_admin_can_retrieve_all_message_receipts(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/message-receipts');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'message_id',
                        'recipient_type',
                        'recipient_id',
                        'delivered_at',
                        'read_at',
                        'acknowledged_at',
                        'links'
                    ]
                ],
                'meta'
            ]);
    }

    /**
     * Test creating a message receipt as admin.
     *
     * @return void
     */
    public function test_admin_can_create_message_receipt(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'message_id' => 1,
            'recipient_type' => 'staff',
            'recipient_id' => 1,
        ];
        
        $response = $this->postJson('/api/message-receipts', $data);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'message_id',
                    'recipient_type',
                    'recipient_id'
                ]
            ]);
    }

    /**
     * Test validation fails when creating with invalid data.
     *
     * @return void
     */
    public function test_validation_fails_when_creating_with_invalid_data(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $data = [
            'recipient_type' => 'invalid_type', // Invalid type
            'recipient_id' => -1, // Invalid ID
        ];
        
        $response = $this->postJson('/api/message-receipts', $data);
        
        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors'
            ]);
    }

    /**
     * Test retrieving a specific message receipt.
     *
     * @return void
     */
    public function test_can_retrieve_specific_message_receipt(): void
    {
        $receipt = MessageReceipt::first();
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/message-receipts/{$receipt->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $receipt->id,
                    'message_id' => $receipt->message_id,
                ]
            ]);
    }

    /**
     * Test retrieving non-existent receipt returns 404.
     *
     * @return void
     */
    public function test_retrieving_non_existent_receipt_returns_404(): void
    {
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson('/api/message-receipts/99999');
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false
            ]);
    }

    /**
     * Test marking receipt as delivered.
     *
     * @return void
     */
    public function test_admin_can_mark_receipt_as_delivered(): void
    {
        $receipt = MessageReceipt::factory()->create(['delivered_at' => null]);
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson("/api/message-receipts/{$receipt->id}/mark-as-delivered");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_delivered' => true
                ]
            ]);
    }

    /**
     * Test marking receipt as read.
     *
     * @return void
     */
    public function test_recipient_can_mark_receipt_as_read(): void
    {
        $receipt = MessageReceipt::factory()->create([
            'recipient_type' => 'staff',
            'recipient_id' => $this->user->id,
            'delivered_at' => now(),
            'read_at' => null,
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->postJson("/api/message-receipts/{$receipt->id}/mark-as-read");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_read' => true
                ]
            ]);
    }

    /**
     * Test non-recipient cannot mark receipt as read.
     *
     * @return void
     */
    public function test_non_recipient_cannot_mark_receipt_as_read(): void
    {
        $otherUser = User::factory()->create();
        $receipt = MessageReceipt::factory()->create([
            'recipient_type' => 'staff',
            'recipient_id' => $this->user->id, // Different user
            'delivered_at' => now(),
            'read_at' => null,
        ]);
        
        Sanctum::actingAs($otherUser);
        
        $response = $this->postJson("/api/message-receipts/{$receipt->id}/mark-as-read");
        
        // This should fail due to policy
        $response->assertStatus(403);
    }

    /**
     * Test bulk update status with valid data.
     *
     * @return void
     */
    public function test_bulk_update_status_successfully(): void
    {
        $receipts = MessageReceipt::factory()->count(3)->create(['delivered_at' => now()]);
        $receiptIds = $receipts->pluck('id')->toArray();
        
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->postJson('/api/message-receipts/bulk/update-status', [
            'receipt_ids' => $receiptIds,
            'status' => 'read'
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ]);
    }

    /**
     * Test getting receipts by message ID.
     *
     * @return void
     */
    public function test_get_receipts_by_message_id(): void
    {
        $messageId = 1;
        MessageReceipt::factory()->count(3)->create(['message_id' => $messageId]);
        
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->getJson("/api/message-receipts/message/{$messageId}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'count'
            ]);
    }

    /**
     * Test getting unread count for recipient.
     *
     * @return void
     */
    public function test_get_unread_count_for_recipient(): void
    {
        $recipientType = 'staff';
        $recipientId = $this->user->id;
        
        // Create some unread receipts
        MessageReceipt::factory()->count(2)->create([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'read_at' => null,
        ]);
        
        // Create some read receipts
        MessageReceipt::factory()->count(1)->create([
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'read_at' => now(),
        ]);
        
        Sanctum::actingAs($this->user);
        
        $response = $this->getJson("/api/message-receipts/recipient/{$recipientType}/{$recipientId}/unread-count");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2
                ]
            ]);
    }

    /**
     * Test deleting a message receipt.
     *
     * @return void
     */
    public function test_admin_can_delete_message_receipt(): void
    {
        $receipt = MessageReceipt::factory()->create();
        Sanctum::actingAs($this->adminUser);
        
        $response = $this->deleteJson("/api/message-receipts/{$receipt->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Message receipt deleted successfully.'
            ]);
        
        $this->assertDatabaseMissing('message_receipts', ['id' => $receipt->id]);
    }

    /**
     * Test unauthorized access returns 403.
     *
     * @return void
     */
    public function test_unauthorized_access_returns_403(): void
    {
        $receipt = MessageReceipt::factory()->create();
        
        // Regular user trying to delete (should be unauthorized)
        Sanctum::actingAs($this->user);
        
        $response = $this->deleteJson("/api/message-receipts/{$receipt->id}");
        
        $response->assertStatus(403);
    }
}