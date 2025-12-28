<?php

namespace App\Repositories\MessageReceipt;

use App\Models\MessageReceipt;
use App\Repositories\Contracts\MessageReceiptRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageReceiptRepository implements MessageReceiptRepositoryInterface
{
    /**
     * @var MessageReceipt
     */
    protected MessageReceipt $model;

    /**
     * MessageReceiptRepository constructor.
     *
     * @param MessageReceipt $model
     */
    public function __construct(MessageReceipt $model)
    {
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function find(int $id): ?MessageReceipt
    {
        try {
            return $this->model->with(['message', 'recipient'])->find($id);
        } catch (\Exception $e) {
            // Log the error for internal monitoring
            Log::error('Failed to find message receipt', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(int $id): MessageReceipt
    {
        return $this->model->with(['message', 'recipient'])->findOrFail($id);
    }

    /**
     * {@inheritdoc}
     */
    public function all(array $columns = ['*']): Collection
    {
        try {
            return $this->model->with(['message', 'recipient'])->get($columns);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all message receipts', [
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        try {
            return $this->model->with(['message', 'recipient'])
                ->latest()
                ->paginate($perPage, $columns);
        } catch (\Exception $e) {
            Log::error('Failed to paginate message receipts', [
                'error' => $e->getMessage()
            ]);
            
            // Return empty paginator instead of throwing
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): MessageReceipt
    {
        try {
            DB::beginTransaction();
            
            $receipt = $this->model->create($data);
            
            DB::commit();
            
            return $receipt->load(['message', 'recipient']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to create message receipt', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to create message receipt. Please try again.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(int $id, array $data): MessageReceipt
    {
        try {
            DB::beginTransaction();
            
            $receipt = $this->findOrFail($id);
            $receipt->update($data);
            
            DB::commit();
            
            return $receipt->fresh(['message', 'recipient']);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to update message receipt', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to update message receipt. Please try again.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        try {
            DB::beginTransaction();
            
            $receipt = $this->findOrFail($id);
            $deleted = $receipt->delete();
            
            DB::commit();
            
            return $deleted;
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to delete message receipt', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Failed to delete message receipt. Please try again.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByMessageId(int $messageId): Collection
    {
        try {
            return $this->model->with(['recipient'])
                ->where('message_id', $messageId)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find receipts by message ID', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByRecipient(string $recipientType, int $recipientId): Collection
    {
        try {
            return $this->model->with(['message'])
                ->where('recipient_type', $recipientType)
                ->where('recipient_id', $recipientId)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to find receipts by recipient', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsDelivered(int $id): MessageReceipt
    {
        return $this->update($id, [
            'delivered_at' => now(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsRead(int $id): MessageReceipt
    {
        $data = ['read_at' => now()];
        
        // If not already delivered, mark as delivered too
        $receipt = $this->findOrFail($id);
        if (!$receipt->delivered_at) {
            $data['delivered_at'] = now();
        }
        
        return $this->update($id, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsAcknowledged(int $id): MessageReceipt
    {
        $data = ['acknowledged_at' => now()];
        
        // If not already read, mark as read too
        $receipt = $this->findOrFail($id);
        if (!$receipt->read_at) {
            $data['read_at'] = now();
        }
        if (!$receipt->delivered_at) {
            $data['delivered_at'] = now();
        }
        
        return $this->update($id, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function getUnreadReceipts(string $recipientType, int $recipientId): Collection
    {
        try {
            return $this->model->with(['message'])
                ->where('recipient_type', $recipientType)
                ->where('recipient_id', $recipientId)
                ->whereNull('read_at')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get unread receipts', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUndeliveredReceipts(int $messageId): Collection
    {
        try {
            return $this->model->with(['recipient'])
                ->where('message_id', $messageId)
                ->whereNull('delivered_at')
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to get undelivered receipts', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return new Collection();
        }
    }
}