<?php

namespace Tests\Unit;

use App\Models\MessageReceipt;
use App\Repositories\Contracts\MessageReceiptRepositoryInterface;
use App\Services\MessageReceipt\MessageReceiptService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MessageReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var MessageReceiptService
     */
    protected MessageReceiptService $service;

    /**
     * @var Mockery\MockInterface|MessageReceiptRepositoryInterface
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
        
        $this->repositoryMock = Mockery::mock(MessageReceiptRepositoryInterface::class);
        $this->service = new MessageReceiptService($this->repositoryMock);
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
     * Test getting all receipts successfully.
     *
     * @return void
     */
    public function test_get_all_receipts_successfully(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        
        $this->repositoryMock
            ->shouldReceive('paginate')
            ->once()
            ->with(15, ['*'])
            ->andReturn($paginator);
        
        $result = $this->service->getAllReceipts();
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Message receipts retrieved successfully.', $result['message']);
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $result['data']);
    }

    /**
     * Test getting receipt by ID successfully.
     *
     * @return void
     */
    public function test_get_receipt_by_id_successfully(): void
    {
        $receipt = MessageReceipt::factory()->make(['id' => 1]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($receipt);
        
        $result = $this->service->getReceiptById(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Message receipt retrieved successfully.', $result['message']);
        $this->assertEquals($receipt, $result['data']);
    }

    /**
     * Test getting non-existent receipt returns error.
     *
     * @return void
     */
    public function test_get_non_existent_receipt_returns_error(): void
    {
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);
        
        $result = $this->service->getReceiptById(999);
        
        $this->assertFalse($result['success']);
        $this->assertEquals('Message receipt not found.', $result['message']);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    /**
     * Test creating a receipt successfully.
     *
     * @return void
     */
    public function test_create_receipt_successfully(): void
    {
        $data = [
            'message_id' => 1,
            'recipient_type' => 'staff',
            'recipient_id' => 1,
        ];
        
        $receipt = MessageReceipt::factory()->make($data);
        
        // Mock recipient validation (simplified)
        // In real test, you'd mock the actual validation method
        
        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($receipt);
        
        $result = $this->service->createReceipt($data);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Message receipt created successfully.', $result['message']);
    }

    /**
     * Test creating duplicate receipt returns error.
     *
     * @return void
     */
    public function test_create_duplicate_receipt_returns_error(): void
    {
        $data = [
            'message_id' => 1,
            'recipient_type' => 'staff',
            'recipient_id' => 1,
        ];
        
        $existingReceipts = new Collection([MessageReceipt::factory()->make(['message_id' => 1])]);
        
        $this->repositoryMock
            ->shouldReceive('findByRecipient')
            ->once()
            ->with('staff', 1)
            ->andReturn($existingReceipts);
        
        $result = $this->service->createReceipt($data);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already exists', $result['message']);
    }

    /**
     * Test marking receipt as delivered successfully.
     *
     * @return void
     */
    public function test_mark_as_delivered_successfully(): void
    {
        $receipt = MessageReceipt::factory()->make([
            'id' => 1,
            'delivered_at' => null,
        ]);
        
        $updatedReceipt = MessageReceipt::factory()->make([
            'id' => 1,
            'delivered_at' => now(),
        ]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($receipt);
        
        $this->repositoryMock
            ->shouldReceive('markAsDelivered')
            ->once()
            ->with(1)
            ->andReturn($updatedReceipt);
        
        $result = $this->service->markAsDelivered(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Message receipt marked as delivered successfully.', $result['message']);
    }

    /**
     * Test marking already delivered receipt returns error.
     *
     * @return void
     */
    public function test_mark_already_delivered_receipt_returns_error(): void
    {
        $receipt = MessageReceipt::factory()->make([
            'id' => 1,
            'delivered_at' => now()->subHour(),
        ]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($receipt);
        
        $result = $this->service->markAsDelivered(1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already marked', $result['message']);
    }

    /**
     * Test marking receipt as read successfully.
     *
     * @return void
     */
    public function test_mark_as_read_successfully(): void
    {
        $receipt = MessageReceipt::factory()->make([
            'id' => 1,
            'delivered_at' => now()->subHour(),
            'read_at' => null,
        ]);
        
        $updatedReceipt = MessageReceipt::factory()->make([
            'id' => 1,
            'read_at' => now(),
        ]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($receipt);
        
        $this->repositoryMock
            ->shouldReceive('markAsRead')
            ->once()
            ->with(1)
            ->andReturn($updatedReceipt);
        
        $result = $this->service->markAsRead(1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('Message receipt marked as read successfully.', $result['message']);
    }

    /**
     * Test marking receipt as read before delivery returns error.
     *
     * @return void
     */
    public function test_mark_as_read_before_delivery_returns_error(): void
    {
        $receipt = MessageReceipt::factory()->make([
            'id' => 1,
            'delivered_at' => null,
            'read_at' => null,
        ]);
        
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($receipt);
        
        $result = $this->service->markAsRead(1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('before delivery', $result['message']);
    }

    /**
     * Test bulk update status with valid data.
     *
     * @return void
     */
    public function test_bulk_update_status_successfully(): void
    {
        $receiptIds = [1, 2, 3];
        $status = 'read';
        
        // Mock individual status updates
        $this->repositoryMock
            ->shouldReceive('find')
            ->times(3)
            ->andReturn(
                MessageReceipt::factory()->make(['id' => 1, 'delivered_at' => now()]),
                MessageReceipt::factory()->make(['id' => 2, 'delivered_at' => now()]),
                MessageReceipt::factory()->make(['id' => 3, 'delivered_at' => now()])
            );
        
        $this->repositoryMock
            ->shouldReceive('markAsRead')
            ->times(3)
            ->andReturn(
                MessageReceipt::factory()->make(['id' => 1]),
                MessageReceipt::factory()->make(['id' => 2]),
                MessageReceipt::factory()->make(['id' => 3])
            );
        
        $result = $this->service->bulkUpdateStatus($receiptIds, $status);
        
        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['data']['successful']);
    }

    /**
     * Test bulk update with too many IDs returns error.
     *
     * @return void
     */
    public function test_bulk_update_with_too_many_ids_returns_error(): void
    {
        $receiptIds = range(1, 101); // 101 IDs
        $status = 'read';
        
        $result = $this->service->bulkUpdateStatus($receiptIds, $status);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Batch size too large', $result['message']);
    }

    /**
     * Test getting unread count successfully.
     *
     * @return void
     */
    public function test_get_unread_count_successfully(): void
    {
        $unreadReceipts = new Collection([
            MessageReceipt::factory()->make(),
            MessageReceipt::factory()->make(),
        ]);
        
        $this->repositoryMock
            ->shouldReceive('getUnreadReceipts')
            ->once()
            ->with('staff', 1)
            ->andReturn($unreadReceipts);
        
        $result = $this->service->getUnreadCount('staff', 1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['data']['count']);
    }

    /**
     * Test getting unread count with invalid recipient type returns error.
     *
     * @return void
     */
    public function test_get_unread_count_invalid_recipient_type_returns_error(): void
    {
        $result = $this->service->getUnreadCount('invalid', 1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid recipient type', $result['message']);
    }
}