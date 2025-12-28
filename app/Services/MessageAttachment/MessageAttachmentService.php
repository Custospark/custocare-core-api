<?php

namespace App\Services\MessageAttachment;

use App\Models\MessageAttachment;
use App\Repositories\Contracts\MessageAttachmentRepositoryInterface;
use App\Services\Contracts\MessageAttachmentServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageAttachmentService implements MessageAttachmentServiceInterface
{
    /**
     * Maximum file size in bytes (10MB)
     */
    private const MAX_FILE_SIZE = 10485760;

    /**
     * Allowed MIME types for each attachment type
     */
    private const ALLOWED_MIME_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'pdf' => ['application/pdf'],
        'lab_result' => ['application/pdf', 'text/plain', 'text/csv'],
        'radiology_image' => ['image/dicom', 'image/jpeg', 'image/png'],
        'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg'],
        'video' => ['video/mp4', 'video/avi', 'video/mov'],
        'other' => ['*'], // Accept all types
    ];

    /**
     * Create a new service instance.
     *
     * @param MessageAttachmentRepositoryInterface $repository
     */
    public function __construct(
        private readonly MessageAttachmentRepositoryInterface $repository
    ) {}

    /**
     * Get all message attachments.
     *
     * @param int $perPage
     * @return array
     */
    public function getAllAttachments(int $perPage = 15): array
    {
        try {
            $attachments = $this->repository->getAll($perPage);
            
            return [
                'success' => true,
                'data' => $attachments,
                'message' => 'Attachments retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attachments', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve attachments',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Get a specific message attachment by ID.
     *
     * @param int $id
     * @return array
     */
    public function getAttachmentById(int $id): array
    {
        try {
            $attachment = $this->repository->findById($id);
            
            if (!$attachment) {
                return [
                    'success' => false,
                    'message' => 'Attachment not found',
                    'errors' => ['id' => 'The specified attachment does not exist'],
                ];
            }
            
            return [
                'success' => true,
                'data' => $attachment,
                'message' => 'Attachment retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attachment by ID', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Get a specific message attachment by UUID.
     *
     * @param string $uuid
     * @return array
     */
    public function getAttachmentByUuid(string $uuid): array
    {
        try {
            $attachment = $this->repository->findByUuid($uuid);
            
            if (!$attachment) {
                return [
                    'success' => false,
                    'message' => 'Attachment not found',
                    'errors' => ['uuid' => 'The specified attachment does not exist'],
                ];
            }
            
            return [
                'success' => true,
                'data' => $attachment,
                'message' => 'Attachment retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attachment by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Get attachments for a specific message.
     *
     * @param int $messageId
     * @return array
     */
    public function getAttachmentsByMessage(int $messageId): array
    {
        try {
            $attachments = $this->repository->findByMessageId($messageId);
            
            return [
                'success' => true,
                'data' => $attachments,
                'message' => 'Message attachments retrieved successfully',
                'meta' => [
                    'count' => $attachments->count(),
                    'message_id' => $messageId,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attachments by message', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve message attachments',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Create a new message attachment.
     *
     * @param array $data
     * @return array
     */
    public function createAttachment(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Validate attachment type
            if (!$this->validateAttachmentType($data['attachment_type'])) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Invalid attachment type',
                    'errors' => ['attachment_type' => 'The specified attachment type is not allowed'],
                ];
            }
            
            // Check for duplicate file based on checksum
            $duplicateCheck = $this->checkFileDuplicate($data['checksum']);
            
            if ($duplicateCheck['exists']) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Duplicate file detected',
                    'errors' => ['file' => 'A file with the same content already exists'],
                    'duplicate' => $duplicateCheck['attachment'],
                ];
            }
            
            // Generate UUID if not provided
            if (!isset($data['attachment_uuid'])) {
                $data['attachment_uuid'] = Str::uuid()->toString();
            }
            
            $attachment = $this->repository->create($data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $attachment,
                'message' => 'Attachment created successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create message attachment', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Update an existing message attachment.
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public function updateAttachment(int $id, array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Check if attachment exists
            $existingAttachment = $this->repository->findById($id);
            
            if (!$existingAttachment) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Attachment not found',
                    'errors' => ['id' => 'The specified attachment does not exist'],
                ];
            }
            
            // Validate attachment type if being updated
            if (isset($data['attachment_type']) && !$this->validateAttachmentType($data['attachment_type'])) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Invalid attachment type',
                    'errors' => ['attachment_type' => 'The specified attachment type is not allowed'],
                ];
            }
            
            // Check for duplicate checksum if being updated
            if (isset($data['checksum'])) {
                $duplicateCheck = $this->checkFileDuplicate($data['checksum'], $id);
                
                if ($duplicateCheck['exists']) {
                    DB::rollBack();
                    
                    return [
                        'success' => false,
                        'message' => 'Duplicate file detected',
                        'errors' => ['file' => 'A file with the same content already exists'],
                        'duplicate' => $duplicateCheck['attachment'],
                    ];
                }
            }
            
            $attachment = $this->repository->update($id, $data);
            
            DB::commit();
            
            return [
                'success' => true,
                'data' => $attachment,
                'message' => 'Attachment updated successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update message attachment', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Delete a message attachment.
     *
     * @param int $id
     * @return array
     */
    public function deleteAttachment(int $id): array
    {
        DB::beginTransaction();
        
        try {
            // Check if attachment exists
            $attachment = $this->repository->findById($id);
            
            if (!$attachment) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Attachment not found',
                    'errors' => ['id' => 'The specified attachment does not exist'],
                ];
            }
            
            // Delete the physical file from storage
            $fileDeleted = $this->deletePhysicalFile(
                $attachment->storage_disk,
                $attachment->storage_path
            );
            
            if (!$fileDeleted) {
                Log::warning('Physical file not found during attachment deletion', [
                    'attachment_id' => $id,
                    'storage_path' => $attachment->storage_path,
                ]);
            }
            
            // Delete the database record
            $deleted = $this->repository->delete($id);
            
            if (!$deleted) {
                DB::rollBack();
                
                return [
                    'success' => false,
                    'message' => 'Failed to delete attachment',
                    'errors' => ['server' => 'Unable to delete attachment record'],
                ];
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Attachment deleted successfully',
                'data' => [
                    'id' => $id,
                    'file_deleted' => $fileDeleted,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete message attachment', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete attachment',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Process and store an uploaded file as message attachment.
     *
     * @param UploadedFile $file
     * @param int $messageId
     * @param string $attachmentType
     * @param bool $containsPhi
     * @return array
     */
    public function processFileUpload(
        UploadedFile $file,
        int $messageId,
        string $attachmentType,
        bool $containsPhi = true
    ): array {
        try {
            // Validate file size
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                return [
                    'success' => false,
                    'message' => 'File size exceeds maximum limit',
                    'errors' => ['file' => 'Maximum file size is 10MB'],
                ];
            }
            
            // Validate attachment type
            if (!$this->validateAttachmentType($attachmentType)) {
                return [
                    'success' => false,
                    'message' => 'Invalid attachment type',
                    'errors' => ['attachment_type' => 'The specified attachment type is not allowed'],
                ];
            }
            
            // Validate MIME type
            $mimeType = $file->getMimeType();
            $allowedMimes = self::ALLOWED_MIME_TYPES[$attachmentType];
            
            if ($allowedMimes !== ['*'] && !in_array($mimeType, $allowedMimes, true)) {
                return [
                    'success' => false,
                    'message' => 'Invalid file type for this attachment type',
                    'errors' => ['file' => 'The file type is not allowed for ' . $attachmentType],
                ];
            }
            
            // Generate file metadata
            $originalName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $checksum = hash_file('sha256', $file->getRealPath());
            
            // Check for duplicate file
            $duplicateCheck = $this->checkFileDuplicate($checksum);
            
            if ($duplicateCheck['exists']) {
                return [
                    'success' => false,
                    'message' => 'Duplicate file detected',
                    'errors' => ['file' => 'A file with the same content already exists'],
                    'duplicate' => $duplicateCheck['attachment'],
                ];
            }
            
            // Determine storage disk (use 'local' for PHI, 'public' for non-PHI)
            $storageDisk = $containsPhi ? 'local' : 'public';
            
            // Generate storage path
            $storagePath = $this->generateStoragePath(
                $messageId,
                $attachmentType,
                $originalName
            );
            
            // Store the file
            $storedPath = $file->storeAs(
                dirname($storagePath),
                basename($storagePath),
                $storageDisk
            );
            
            if (!$storedPath) {
                return [
                    'success' => false,
                    'message' => 'Failed to store file',
                    'errors' => ['file' => 'Unable to save file to storage'],
                ];
            }
            
            // Create attachment record
            $attachmentData = [
                'attachment_uuid' => Str::uuid()->toString(),
                'message_id' => $messageId,
                'attachment_type' => $attachmentType,
                'file_name' => $originalName,
                'mime_type' => $mimeType,
                'file_size_bytes' => $fileSize,
                'storage_disk' => $storageDisk,
                'storage_path' => $storedPath,
                'contains_phi' => $containsPhi,
                'checksum' => $checksum,
            ];
            
            $attachment = $this->repository->create($attachmentData);
            
            return [
                'success' => true,
                'data' => $attachment,
                'message' => 'File uploaded and processed successfully',
                'file_info' => [
                    'original_name' => $originalName,
                    'size' => $fileSize,
                    'mime_type' => $mimeType,
                    'storage_path' => $storedPath,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process file upload', [
                'message_id' => $messageId,
                'attachment_type' => $attachmentType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to process file upload',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Get attachments by type.
     *
     * @param string $type
     * @param int $perPage
     * @return array
     */
    public function getAttachmentsByType(string $type, int $perPage = 15): array
    {
        try {
            if (!$this->validateAttachmentType($type)) {
                return [
                    'success' => false,
                    'message' => 'Invalid attachment type',
                    'errors' => ['type' => 'The specified attachment type is not allowed'],
                ];
            }
            
            $attachments = $this->repository->findByType($type, $perPage);
            
            return [
                'success' => true,
                'data' => $attachments,
                'message' => ucfirst($type) . ' attachments retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attachments by type', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve attachments by type',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Get statistics about attachments.
     *
     * @return array
     */
    public function getAttachmentStatistics(): array
    {
        try {
            $totalAttachments = MessageAttachment::count();
            $totalStorage = $this->repository->getTotalStorageUsed();
            $phiAttachments = MessageAttachment::where('contains_phi', true)->count();
            
            $typeDistribution = MessageAttachment::select('attachment_type', DB::raw('COUNT(*) as count'))
                ->groupBy('attachment_type')
                ->pluck('count', 'attachment_type')
                ->toArray();
            
            $recentAttachments = MessageAttachment::orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            
            return [
                'success' => true,
                'data' => [
                    'total_attachments' => $totalAttachments,
                    'total_storage_bytes' => $totalStorage,
                    'total_storage_human' => $this->formatBytes($totalStorage),
                    'phi_attachments' => $phiAttachments,
                    'type_distribution' => $typeDistribution,
                    'recent_attachments' => $recentAttachments,
                ],
                'message' => 'Attachment statistics retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve attachment statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve attachment statistics',
                'errors' => ['server' => 'An internal error occurred'],
            ];
        }
    }

    /**
     * Validate attachment type.
     *
     * @param string $type
     * @return bool
     */
    public function validateAttachmentType(string $type): bool
    {
        return in_array($type, MessageAttachment::getAttachmentTypes(), true);
    }

    /**
     * Check if file with same checksum already exists.
     *
     * @param string $checksum
     * @param int|null $excludeId
     * @return array
     */
    public function checkFileDuplicate(string $checksum, ?int $excludeId = null): array
    {
        try {
            $exists = $this->repository->checksumExists($checksum, $excludeId);
            $attachment = null;
            
            if ($exists) {
                $attachment = MessageAttachment::where('checksum', $checksum)->first();
            }
            
            return [
                'exists' => $exists,
                'attachment' => $attachment,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check file duplicate', [
                'checksum' => $checksum,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'exists' => false,
                'attachment' => null,
            ];
        }
    }

    /**
     * Generate storage path for uploaded file.
     *
     * @param int $messageId
     * @param string $attachmentType
     * @param string $originalName
     * @return string
     */
    private function generateStoragePath(
        int $messageId,
        string $attachmentType,
        string $originalName
    ): string {
        $timestamp = now()->format('Y/m/d');
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $uniqueId = Str::random(8);
        
        return sprintf(
            'message-attachments/%s/%d/%s/%s_%s',
            $timestamp,
            $messageId,
            $attachmentType,
            $uniqueId,
            $safeName
        );
    }

    /**
     * Delete physical file from storage.
     *
     * @param string $disk
     * @param string $path
     * @return bool
     */
    private function deletePhysicalFile(string $disk, string $path): bool
    {
        try {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->delete($path);
            }
            
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to delete physical file', [
                'disk' => $disk,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Format bytes to human readable format.
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}