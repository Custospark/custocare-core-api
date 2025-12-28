<?php

namespace App\Services\ClinicalDocument;

use App\Models\ClinicalDocument;
use App\Repositories\Contracts\ClinicalDocumentRepositoryInterface;
use App\Services\Contracts\ClinicalDocumentServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ClinicalDocumentService implements ClinicalDocumentServiceInterface
{
    /**
     * @var ClinicalDocumentRepositoryInterface
     */
    protected $repository;

    /**
     * Allowed MIME types for clinical documents
     */
    protected const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/rtf',
    ];

    /**
     * Maximum file size in bytes (50MB)
     */
    protected const MAX_FILE_SIZE = 50 * 1024 * 1024;

    /**
     * Constructor
     *
     * @param ClinicalDocumentRepositoryInterface $repository
     */
    public function __construct(ClinicalDocumentRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllDocuments(array $filters = [], int $perPage = 20): array
    {
        try {
            $documents = $this->repository->getAll($filters, $perPage);
            
            return [
                'success' => true,
                'data' => $documents,
                'message' => 'Clinical documents retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve clinical documents. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentById(int $id): array
    {
        try {
            $document = $this->repository->find($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'data' => $document,
                'message' => 'Clinical document retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical document by ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve clinical document. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentByUuid(string $uuid): array
    {
        try {
            $document = $this->repository->findByUuid($uuid);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'data' => $document,
                'message' => 'Clinical document retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve clinical document by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve clinical document. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getDocumentsByPatient(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $documents = $this->repository->getByPatientId($patientId, $filters, $perPage);
            
            return [
                'success' => true,
                'data' => $documents,
                'message' => 'Patient documents retrieved successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient documents', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve patient documents. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createDocument(array $data, UploadedFile $file): array
    {
        try {
            // Validate file
            $validationResult = $this->validateFile($file);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // Check for duplicate file
            $fileHash = hash_file('sha256', $file->path());
            if ($this->repository->fileHashExists($fileHash, $data['patient_id'] ?? null)) {
                return [
                    'success' => false,
                    'message' => 'This document has already been uploaded for this patient.',
                    'data' => null
                ];
            }
            
            return DB::transaction(function () use ($data, $file, $fileHash) {
                // Generate unique file path
                $fileName = $this->generateFileName($file, $data['patient_id'] ?? null);
                $storagePath = "clinical_documents/" . date('Y/m/d') . "/{$fileName}";
                
                // Store file
                Storage::put($storagePath, file_get_contents($file->path()));
                
                // Prepare document data
                $documentData = array_merge($data, [
                    'document_uuid' => Str::uuid()->toString(),
                    'file_mime_type' => $file->getMimeType(),
                    'file_extension' => $file->getClientOriginalExtension(),
                    'file_size_bytes' => $file->getSize(),
                    'file_storage_path' => $storagePath,
                    'file_hash' => $fileHash,
                    'status' => ClinicalDocument::STATUS_ACTIVE,
                ]);
                
                // Create document record
                $document = $this->repository->create($documentData);
                
                // Log the upload
                Log::info('Clinical document uploaded', [
                    'document_id' => $document->id,
                    'patient_id' => $document->patient_id,
                    'file_size' => $document->file_size_bytes,
                    'uploaded_by' => $data['uploaded_by_staff_id'] ?? 'system'
                ]);
                
                return [
                    'success' => true,
                    'data' => $document,
                    'message' => 'Clinical document uploaded successfully'
                ];
            });
            
        } catch (\Exception $e) {
            Log::error('Failed to create clinical document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to upload clinical document. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateDocument(int $id, array $data): array
    {
        try {
            $document = $this->repository->find($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            // Validate status if being updated
            if (isset($data['status']) && !in_array($data['status'], ClinicalDocument::getValidStatuses())) {
                return [
                    'success' => false,
                    'message' => 'Invalid document status',
                    'data' => null
                ];
            }
            
            // Validate document type if being updated
            if (isset($data['document_type']) && !in_array($data['document_type'], ClinicalDocument::getValidDocumentTypes())) {
                return [
                    'success' => false,
                    'message' => 'Invalid document type',
                    'data' => null
                ];
            }
            
            $updatedDocument = $this->repository->update($id, $data);
            
            Log::info('Clinical document updated', [
                'document_id' => $id,
                'updated_fields' => array_keys($data)
            ]);
            
            return [
                'success' => true,
                'data' => $updatedDocument,
                'message' => 'Clinical document updated successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to update clinical document', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update clinical document. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function deleteDocument(int $id): array
    {
        try {
            $document = $this->repository->find($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            // Update status to entered_in_error instead of deleting
            $this->repository->updateStatus($id, ClinicalDocument::STATUS_ENTERED_IN_ERROR);
            
            Log::info('Clinical document marked as entered in error', [
                'document_id' => $id
            ]);
            
            return [
                'success' => true,
                'message' => 'Clinical document marked as entered in error successfully',
                'data' => null
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to delete clinical document', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to delete clinical document. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateDocumentStatus(int $id, string $status): array
    {
        try {
            if (!in_array($status, ClinicalDocument::getValidStatuses())) {
                return [
                    'success' => false,
                    'message' => 'Invalid document status',
                    'data' => null
                ];
            }
            
            $document = $this->repository->find($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            $success = $this->repository->updateStatus($id, $status);
            
            if (!$success) {
                return [
                    'success' => false,
                    'message' => 'Failed to update document status',
                    'data' => null
                ];
            }
            
            Log::info('Clinical document status updated', [
                'document_id' => $id,
                'status' => $status
            ]);
            
            return [
                'success' => true,
                'message' => 'Document status updated successfully',
                'data' => null
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to update document status', [
                'id' => $id,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to update document status. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function downloadDocument(int $id): array
    {
        try {
            $document = $this->repository->find($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            if (!Storage::exists($document->file_storage_path)) {
                return [
                    'success' => false,
                    'message' => 'Document file not found in storage',
                    'data' => null
                ];
            }
            
            // Verify file integrity
            $currentHash = hash_file('sha256', Storage::path($document->file_storage_path));
            if ($currentHash !== $document->file_hash) {
                Log::warning('Document integrity check failed', [
                    'document_id' => $id,
                    'stored_hash' => $document->file_hash,
                    'current_hash' => $currentHash
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Document integrity verification failed. The file may have been corrupted.',
                    'data' => null
                ];
            }
            
            Log::info('Clinical document downloaded', [
                'document_id' => $id,
                'downloaded_by' => auth::id() ?? 'system'
            ]);
            
            return [
                'success' => true,
                'data' => [
                    'file_path' => $document->file_storage_path,
                    'file_name' => $document->document_name,
                    'mime_type' => $document->file_mime_type,
                    'size' => $document->file_size_bytes
                ],
                'message' => 'Document ready for download'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to download clinical document', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to download document. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function verifyDocumentIntegrity(int $id): array
    {
        try {
            $document = $this->repository->find($id);
            
            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Clinical document not found',
                    'data' => null
                ];
            }
            
            if (!Storage::exists($document->file_storage_path)) {
                return [
                    'success' => false,
                    'message' => 'Document file not found in storage',
                    'data' => null
                ];
            }
            
            $currentHash = hash_file('sha256', Storage::path($document->file_storage_path));
            $integrityVerified = $currentHash === $document->file_hash;
            
            $result = [
                'integrity_verified' => $integrityVerified,
                'stored_hash' => $document->file_hash,
                'current_hash' => $currentHash,
                'last_verified_at' => now()->toISOString()
            ];
            
            if (!$integrityVerified) {
                Log::error('Document integrity verification failed', [
                    'document_id' => $id,
                    'stored_hash' => $document->file_hash,
                    'current_hash' => $currentHash
                ]);
            }
            
            return [
                'success' => true,
                'data' => $result,
                'message' => $integrityVerified 
                    ? 'Document integrity verified successfully' 
                    : 'Document integrity check failed'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to verify document integrity', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to verify document integrity. Please try again later.',
                'data' => null
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getStatistics(?int $facilityId = null): array
    {
        try {
            $query = ClinicalDocument::query();
            
            if ($facilityId) {
                $query->where('facility_id', $facilityId);
            }
            
            $totalDocuments = $query->count();
            $activeDocuments = $query->where('status', ClinicalDocument::STATUS_ACTIVE)->count();
            
            $documentsByType = $query->selectRaw('document_type, COUNT(*) as count')
                ->groupBy('document_type')
                ->pluck('count', 'document_type')
                ->toArray();
            
            $totalStorageUsed = $query->sum('file_size_bytes');
            
            $recentDocuments = $query->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['id', 'document_name', 'document_type', 'created_at']);
            
            return [
                'success' => true,
                'data' => [
                    'total_documents' => $totalDocuments,
                    'active_documents' => $activeDocuments,
                    'documents_by_type' => $documentsByType,
                    'total_storage_bytes' => $totalStorageUsed,
                    'total_storage_human' => $this->bytesToHuman($totalStorageUsed),
                    'recent_documents' => $recentDocuments
                ],
                'message' => 'Statistics retrieved successfully'
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get document statistics', [
                'facility_id' => $facilityId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to retrieve statistics. Please try again later.',
                'data' => []
            ];
        }
    }

    /**
     * Validate uploaded file
     *
     * @param UploadedFile $file
     * @return array
     */
    private function validateFile(UploadedFile $file): array
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            return [
                'success' => false,
                'message' => 'File size exceeds maximum limit of 50MB',
                'data' => null
            ];
        }
        
        // Check MIME type
        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            return [
                'success' => false,
                'message' => 'File type not allowed. Allowed types: PDF, JPEG, PNG, TIFF, DOC, DOCX, TXT, RTF',
                'data' => null
            ];
        }
        
        // Check file extension
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'tiff', 'tif', 'doc', 'docx', 'txt', 'rtf'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'success' => false,
                'message' => 'File extension not allowed',
                'data' => null
            ];
        }
        
        return ['success' => true];
    }

    /**
     * Generate unique file name
     *
     * @param UploadedFile $file
     * @param int|null $patientId
     * @return string
     */
    private function generateFileName(UploadedFile $file, ?int $patientId): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $randomString = Str::random(10);
        
        $safeName = Str::slug($originalName);
        
        if ($patientId) {
            return "patient_{$patientId}_{$safeName}_{$timestamp}_{$randomString}.{$extension}";
        }
        
        return "{$safeName}_{$timestamp}_{$randomString}.{$extension}";
    }

    /**
     * Convert bytes to human readable format
     *
     * @param int $bytes
     * @return string
     */
    private function bytesToHuman(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}