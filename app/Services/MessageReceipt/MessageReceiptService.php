<?php

namespace App\Services\MessageReceipt;

use App\Repositories\Contracts\MessageReceiptRepositoryInterface;
use App\Services\Contracts\MessageReceiptServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageReceiptService implements MessageReceiptServiceInterface
{
    /**
     * @var MessageReceiptRepositoryInterface
     */
    protected MessageReceiptRepositoryInterface $repository;

    /**
     * MessageReceiptService constructor.
     *
     * @param MessageReceiptRepositoryInterface $repository
     */
    public function __construct(MessageReceiptRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllReceipts(int $perPage = 15): array
    {
        try {
            $receipts = $this->repository->paginate($perPage);
            
            return [
                'success' => true,
                'data' => $receipts,
                'message' => 'Message receipts retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve all receipts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve message receipts. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getReceiptById(int $id): array
    {
        try {
            $receipt = $this->repository->find($id);
            
            if (!$receipt) {
                return [
                    'success' => false,
                    'message' => 'Message receipt not found.',
                    'errors' => ['id' => 'The specified message receipt does not exist.']
                ];
            }
            
            return [
                'success' => true,
                'data' => $receipt,
                'message' => 'Message receipt retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve receipt by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve message receipt. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createReceipt(array $data): array
    {
        // Validate recipient existence (simplified)
        $recipientExists = $this->validateRecipientExists($data['recipient_type'], $data['recipient_id']);
        
        if (!$recipientExists) {
            return [
                'success' => false,
                'message' => 'Invalid recipient specified.',
                'errors' => [
                    'recipient_id' => 'The specified recipient does not exist.',
                    'recipient_type' => 'Invalid recipient type or ID.'
                ]
            ];
        }
        
        // Check for duplicate receipt
        $duplicateExists = $this->checkForDuplicateReceipt(
            $data['message_id'],
            $data['recipient_type'],
            $data['recipient_id']
        );
        
        if ($duplicateExists) {
            return [
                'success' => false,
                'message' => 'A receipt already exists for this recipient and message.',
                'errors' => [
                    'recipient_id' => 'Duplicate receipt not allowed for same recipient and message.'
                ]
            ];
        }
        
        try {
            $receipt = $this->repository->create($data);
            
            return [
                'success' => true,
                'data' => $receipt,
                'message' => 'Message receipt created successfully.',
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create message receipt', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create message receipt. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateReceipt(int $id, array $data): array
    {
        try {
            // Check if receipt exists
            $existingReceipt = $this->repository->find($id);
            if (!$existingReceipt) {
                return [
                    'success' => false,
                    'message' => 'Message receipt not found.',
                    'errors' => ['id' => 'The specified message receipt does not exist.']
                ];
            }
            
            // Business rule: Cannot change recipient once created
            if (isset($data['recipient_type']) || isset($data['recipient_id'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot change recipient for an existing receipt.',
                    'errors' => [
                        'recipient_type' => 'Recipient cannot be modified.',
                        'recipient_id' => 'Recipient cannot be modified.'
                    ]
                ];
            }
            
            // Business rule: Cannot change message_id once created
            if (isset($data['message_id'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot change message for an existing receipt.',
                    'errors' => ['message_id' => 'Message cannot be modified.']
                ];
            }
            
            // Business rule: Status transitions validation
            $validationResult = $this->validateStatusTransition($existingReceipt, $data);
            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => 'Invalid status transition.',
                    'errors' => $validationResult['errors']
                ];
            }
            
            $receipt = $this->repository->update($id, $data);
            
            return [
                'success' => true,
                'data' => $receipt,
                'message' => 'Message receipt updated successfully.',
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to update message receipt', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update message receipt. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteReceipt(int $id): array
    {
        try {
            // Check if receipt exists
            $existingReceipt = $this->repository->find($id);
            if (!$existingReceipt) {
                return [
                    'success' => false,
                    'message' => 'Message receipt not found.',
                    'errors' => ['id' => 'The specified message receipt does not exist.']
                ];
            }
            
            // Business rule: Cannot delete delivered receipts after 24 hours
            if ($existingReceipt->delivered_at && 
                $existingReceipt->delivered_at->diffInHours(now()) > 24) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete receipts that were delivered more than 24 hours ago.',
                    'errors' => ['general' => 'Deletion not allowed for old delivered receipts.']
                ];
            }
            
            $deleted = $this->repository->delete($id);
            
            if (!$deleted) {
                return [
                    'success' => false,
                    'message' => 'Failed to delete message receipt.',
                    'errors' => ['general' => 'Deletion failed. Please try again.']
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Message receipt deleted successfully.',
                'data' => null
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete message receipt', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete message receipt. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getReceiptsByMessage(int $messageId): array
    {
        try {
            $receipts = $this->repository->findByMessageId($messageId);
            
            return [
                'success' => true,
                'data' => $receipts,
                'count' => $receipts->count(),
                'message' => 'Message receipts retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve receipts by message', [
                'message_id' => $messageId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve message receipts. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getReceiptsByRecipient(string $recipientType, int $recipientId): array
    {
        // Validate recipient type
        if (!in_array($recipientType, ['staff', 'patient'])) {
            return [
                'success' => false,
                'message' => 'Invalid recipient type.',
                'errors' => ['recipient_type' => 'Recipient type must be either "staff" or "patient".']
            ];
        }
        
        try {
            $receipts = $this->repository->findByRecipient($recipientType, $recipientId);
            
            return [
                'success' => true,
                'data' => $receipts,
                'count' => $receipts->count(),
                'message' => 'Recipient receipts retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve receipts by recipient', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve recipient receipts. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsDelivered(int $id): array
    {
        try {
            $receipt = $this->repository->find($id);
            
            if (!$receipt) {
                return [
                    'success' => false,
                    'message' => 'Message receipt not found.',
                    'errors' => ['id' => 'The specified message receipt does not exist.']
                ];
            }
            
            // Business rule: Cannot mark as delivered if already delivered
            if ($receipt->isDelivered()) {
                return [
                    'success' => false,
                    'message' => 'Receipt is already marked as delivered.',
                    'errors' => ['delivered_at' => 'Already delivered.']
                ];
            }
            
            $updatedReceipt = $this->repository->markAsDelivered($id);
            
            return [
                'success' => true,
                'data' => $updatedReceipt,
                'message' => 'Message receipt marked as delivered successfully.',
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark receipt as delivered', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to mark receipt as delivered. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsRead(int $id): array
    {
        try {
            $receipt = $this->repository->find($id);
            
            if (!$receipt) {
                return [
                    'success' => false,
                    'message' => 'Message receipt not found.',
                    'errors' => ['id' => 'The specified message receipt does not exist.']
                ];
            }
            
            // Business rule: Must be delivered before it can be read
            if (!$receipt->isDelivered()) {
                return [
                    'success' => false,
                    'message' => 'Cannot mark as read before delivery.',
                    'errors' => ['read_at' => 'Message must be delivered before it can be read.']
                ];
            }
            
            // Business rule: Cannot mark as read if already read
            if ($receipt->isRead()) {
                return [
                    'success' => false,
                    'message' => 'Receipt is already marked as read.',
                    'errors' => ['read_at' => 'Already read.']
                ];
            }
            
            $updatedReceipt = $this->repository->markAsRead($id);
            
            return [
                'success' => true,
                'data' => $updatedReceipt,
                'message' => 'Message receipt marked as read successfully.',
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark receipt as read', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to mark receipt as read. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function markAsAcknowledged(int $id): array
    {
        try {
            $receipt = $this->repository->find($id);
            
            if (!$receipt) {
                return [
                    'success' => false,
                    'message' => 'Message receipt not found.',
                    'errors' => ['id' => 'The specified message receipt does not exist.']
                ];
            }
            
            // Business rule: Must be read before it can be acknowledged
            if (!$receipt->isRead()) {
                return [
                    'success' => false,
                    'message' => 'Cannot mark as acknowledged before reading.',
                    'errors' => ['acknowledged_at' => 'Message must be read before it can be acknowledged.']
                ];
            }
            
            // Business rule: Cannot mark as acknowledged if already acknowledged
            if ($receipt->isAcknowledged()) {
                return [
                    'success' => false,
                    'message' => 'Receipt is already marked as acknowledged.',
                    'errors' => ['acknowledged_at' => 'Already acknowledged.']
                ];
            }
            
            $updatedReceipt = $this->repository->markAsAcknowledged($id);
            
            return [
                'success' => true,
                'data' => $updatedReceipt,
                'message' => 'Message receipt marked as acknowledged successfully.',
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['general' => $e->getMessage()]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark receipt as acknowledged', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to mark receipt as acknowledged. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function bulkUpdateStatus(array $receiptIds, string $status): array
    {
        // Validate status
        $validStatuses = ['delivered', 'read', 'acknowledged'];
        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'message' => 'Invalid status specified.',
                'errors' => ['status' => 'Status must be one of: ' . implode(', ', $validStatuses)]
            ];
        }
        
        // Limit batch size for performance
        if (count($receiptIds) > 100) {
            return [
                'success' => false,
                'message' => 'Batch size too large. Maximum 100 receipts per batch.',
                'errors' => ['receipt_ids' => 'Maximum 100 receipts allowed per batch operation.']
            ];
        }
        
        $results = [
            'successful' => [],
            'failed' => []
        ];
        
        try {
            DB::beginTransaction();
            
            foreach ($receiptIds as $receiptId) {
                try {
                    switch ($status) {
                        case 'delivered':
                            $result = $this->markAsDelivered($receiptId);
                            break;
                        case 'read':
                            $result = $this->markAsRead($receiptId);
                            break;
                        case 'acknowledged':
                            $result = $this->markAsAcknowledged($receiptId);
                            break;
                        default:
                            $result = [
                                'success' => false,
                                'message' => 'Invalid status'
                            ];
                    }
                    
                    if ($result['success']) {
                        $results['successful'][] = $receiptId;
                    } else {
                        $results['failed'][] = [
                            'id' => $receiptId,
                            'error' => $result['message']
                        ];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'id' => $receiptId,
                        'error' => 'Processing error'
                    ];
                    Log::error('Error processing bulk update for receipt', [
                        'id' => $receiptId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $results,
                'message' => sprintf(
                    'Bulk update completed. Successful: %d, Failed: %d',
                    count($results['successful']),
                    count($results['failed'])
                ),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to perform bulk status update', [
                'receipt_ids' => $receiptIds,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to perform bulk update. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUnreadCount(string $recipientType, int $recipientId): array
    {
        // Validate recipient type
        if (!in_array($recipientType, ['staff', 'patient'])) {
            return [
                'success' => false,
                'message' => 'Invalid recipient type.',
                'errors' => ['recipient_type' => 'Recipient type must be either "staff" or "patient".']
            ];
        }
        
        try {
            $unreadReceipts = $this->repository->getUnreadReceipts($recipientType, $recipientId);
            
            return [
                'success' => true,
                'data' => [
                    'count' => $unreadReceipts->count(),
                    'recipient_type' => $recipientType,
                    'recipient_id' => $recipientId
                ],
                'message' => 'Unread count retrieved successfully.',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get unread count', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Unable to retrieve unread count. Please try again later.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    /**
     * Validate that a recipient exists.
     *
     * @param string $recipientType
     * @param int $recipientId
     * @return bool
     */
    private function validateRecipientExists(string $recipientType, int $recipientId): bool
    {
        try {
            // This is a simplified check. In a real application, you would:
            // 1. Have Staff and Patient models
            // 2. Check if the recipient exists in the appropriate table
            // For now, we'll assume it exists if the type is valid
            
            return in_array($recipientType, ['staff', 'patient']) && $recipientId > 0;
        } catch (\Exception $e) {
            Log::error('Failed to validate recipient existence', [
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Check for duplicate receipt.
     *
     * @param int $messageId
     * @param string $recipientType
     * @param int $recipientId
     * @return bool
     */
    private function checkForDuplicateReceipt(int $messageId, string $recipientType, int $recipientId): bool
    {
        try {
            $existingReceipts = $this->repository->findByRecipient($recipientType, $recipientId);
            
            return $existingReceipts->contains('message_id', $messageId);
        } catch (\Exception $e) {
            Log::error('Failed to check for duplicate receipt', [
                'message_id' => $messageId,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'error' => $e->getMessage()
            ]);
            
            // In case of error, assume no duplicate to allow creation
            return false;
        }
    }

    /**
     * Validate status transition.
     *
     * @param \App\Models\MessageReceipt $receipt
     * @param array $data
     * @return array
     */
    private function validateStatusTransition(\App\Models\MessageReceipt $receipt, array $data): array
    {
        $errors = [];
        
        // Check delivered_at transition
        if (isset($data['delivered_at'])) {
            if ($receipt->delivered_at && $data['delivered_at'] < $receipt->delivered_at) {
                $errors['delivered_at'] = 'Cannot set delivery time earlier than current delivery time.';
            }
        }
        
        // Check read_at transition
        if (isset($data['read_at'])) {
            if (!$receipt->delivered_at && !isset($data['delivered_at'])) {
                $errors['read_at'] = 'Cannot mark as read before delivery.';
            }
            
            if ($receipt->read_at && $data['read_at'] < $receipt->read_at) {
                $errors['read_at'] = 'Cannot set read time earlier than current read time.';
            }
        }
        
        // Check acknowledged_at transition
        if (isset($data['acknowledged_at'])) {
            if (!$receipt->read_at && !isset($data['read_at'])) {
                $errors['acknowledged_at'] = 'Cannot mark as acknowledged before reading.';
            }
            
            if ($receipt->acknowledged_at && $data['acknowledged_at'] < $receipt->acknowledged_at) {
                $errors['acknowledged_at'] = 'Cannot set acknowledged time earlier than current acknowledged time.';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}