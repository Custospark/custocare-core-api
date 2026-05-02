<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClinicalNote;
use App\Models\Patient;
use App\Models\Visit;
use App\Repositories\Contracts\ClinicalNoteRepositoryInterface;
use App\Services\Contracts\ClinicalNoteServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ClinicalNoteService implements ClinicalNoteServiceInterface
{
    /**
     * @var ClinicalNoteRepositoryInterface
     */
    protected ClinicalNoteRepositoryInterface $clinicalNoteRepository;

    /**
     * Constructor.
     *
     * @param ClinicalNoteRepositoryInterface $clinicalNoteRepository
     */
    public function __construct(ClinicalNoteRepositoryInterface $clinicalNoteRepository)
    {
        $this->clinicalNoteRepository = $clinicalNoteRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllNotes(array $filters = [], int $perPage = 20): array
    {
        try {
            $notes = $this->clinicalNoteRepository->getAllPaginated($filters, $perPage);

            return [
                'success' => true,
                'data' => $notes,
                'message' => 'Clinical notes retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get clinical notes', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve clinical notes',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNoteByUuid(string $uuid): array
    {
        try {
            $note = ClinicalNote::where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            return [
                'success' => true,
                'data' => $note,
                'message' => 'Clinical note retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get clinical note by UUID', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNoteById(int $id): array
    {
        try {
            $note = $this->clinicalNoteRepository->findByIdWithRelations($id, ['facility', 'patient', 'staff', 'visit']);

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            return [
                'success' => true,
                'data' => $note,
                'message' => 'Clinical note retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get clinical note by ID', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPatientNotes(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $notes = $this->clinicalNoteRepository->getPaginatedByPatient($patientId, $filters, $perPage);

            return [
                'success' => true,
                'data' => $notes,
                'message' => 'Patient clinical notes retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get patient clinical notes', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve patient clinical notes',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitNotes(int $visitId): array
    {
        try {
            $notes = $this->clinicalNoteRepository->getByVisit($visitId);

            return [
                'success' => true,
                'data' => $notes,
                'message' => 'Visit clinical notes retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit clinical notes', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve visit clinical notes',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createNote(array $data, int $createdByStaffId): array
    {
        DB::beginTransaction();

        try {
            // Validate the data
            $validatedData = $this->validateNoteData($data);

            // Add staff_id and set default noted_at if not provided
            $validatedData['staff_id'] = $createdByStaffId;
            $validatedData['note_status'] = 'active';
            
            if (!isset($validatedData['noted_at'])) {
                $validatedData['noted_at'] = now();
            }

            // Note type and status defaults are handled by model attributes

            $note = $this->clinicalNoteRepository->create($validatedData);

            DB::commit();

            Log::info('Clinical note created successfully', [
                'note_id' => $note->id,
                'patient_id' => $note->patient_id,
                'staff_id' => $createdByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $note,
                'message' => 'Clinical note created successfully',
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'error' => $e->errors(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create clinical note', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateNote(string $uuid, array $data, int $updatedByStaffId): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::where('uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            // Note:Allow editing of notes not in draft states,business rules may chnage in the future.
            // if (!$note->isDraft()) {
            //     return [
            //         'success' => false,
            //         'data' => null,
            //         'message' => 'Only draft notes can be edited',
            //         'error' => 'Note is not in draft status',
            //     ];
            // }

            $validatedData = $this->validateNoteData($data, $note);

            $updated = $this->clinicalNoteRepository->update($note, $validatedData);

            if (!$updated) {
                throw new \Exception('Failed to update clinical note');
            }

            DB::commit();

            Log::info('Clinical note updated successfully', [
                'note_id' => $note->id,
                'updated_by' => $updatedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $note->fresh(),
                'message' => 'Clinical note updated successfully',
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'error' => $e->errors(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteNote(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            $deleted = $this->clinicalNoteRepository->delete($note);

            if (!$deleted) {
                throw new \Exception('Failed to delete clinical note');
            }

            DB::commit();

            Log::info('Clinical note deleted successfully', [
                'note_id' => $note->id,
            ]);

            return [
                'success' => true,
                'message' => 'Clinical note deleted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function restoreNote(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::withTrashed()->where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            $restored = $this->clinicalNoteRepository->restore($note->id);

            if (!$restored) {
                throw new \Exception('Failed to restore clinical note');
            }

            DB::commit();

            Log::info('Clinical note restored successfully', [
                'note_id' => $note->id,
            ]);

            return [
                'success' => true,
                'data' => $note->fresh(),
                'message' => 'Clinical note restored successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to restore clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to restore clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forceDeleteNote(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::withTrashed()->where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            $deleted = $this->clinicalNoteRepository->forceDelete($note->id);

            if (!$deleted) {
                throw new \Exception('Failed to force delete clinical note');
            }

            DB::commit();

            Log::info('Clinical note force deleted successfully', [
                'note_id' => $note->id,
            ]);

            return [
                'success' => true,
                'message' => 'Clinical note permanently deleted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to force delete clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to permanently delete clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finalizeNote(string $uuid): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            if (!$note->isDraft()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Only draft notes can be finalized',
                    'error' => 'Note is not in draft status',
                ];
            }

            $updated = $this->clinicalNoteRepository->updateStatus($note, 'final');

            if (!$updated) {
                throw new \Exception('Failed to finalize clinical note');
            }

            DB::commit();

            Log::info('Clinical note finalized successfully', [
                'note_id' => $note->id,
            ]);

            return [
                'success' => true,
                'data' => $note->fresh(),
                'message' => 'Clinical note finalized successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to finalize clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to finalize clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancelNote(string $uuid, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            if ($note->isFinal()) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Final notes cannot be cancelled. Consider amending instead.',
                    'error' => 'Cannot cancel final note',
                ];
            }

            $updated = $this->clinicalNoteRepository->updateStatus($note, 'cancelled');

            if (!$updated) {
                throw new \Exception('Failed to cancel clinical note');
            }

            // Add cancellation reason to metadata if provided
            if ($reason && $note->structured_data) {
                $structuredData = $note->structured_data ?? [];
                $structuredData['cancellation_reason'] = $reason;
                $structuredData['cancelled_at'] = now()->toISOString();
                $note->update(['structured_data' => $structuredData]);
            }

            DB::commit();

            Log::info('Clinical note cancelled successfully', [
                'note_id' => $note->id,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'data' => $note->fresh(),
                'message' => 'Clinical note cancelled successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to cancel clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to cancel clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function amendNote(string $uuid, array $amendedData, int $amendedByStaffId): array
    {
        DB::beginTransaction();

        try {
            $note = ClinicalNote::where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            if (!$this->canAmendNote($note)) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'This note cannot be amended',
                    'error' => 'Note is not in a state that allows amendment',
                ];
            }

            $validatedData = $this->validateNoteData($amendedData);

            // Create amended note
            $amendedNote = $note->amend($validatedData, $amendedByStaffId);

            DB::commit();

            Log::info('Clinical note amended successfully', [
                'original_note_id' => $note->id,
                'amended_note_id' => $amendedNote->id,
                'amended_by' => $amendedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $amendedNote,
                'message' => 'Clinical note amended successfully',
            ];
        } catch (ValidationException $e) {
            DB::rollBack();
            return [
                'success' => false,
                'data' => null,
                'message' => 'Validation failed',
                'error' => $e->errors(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to amend clinical note', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to amend clinical note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNoteHistory(string $uuid): array
    {
        try {
            $note = ClinicalNote::where('facility_uuid', $uuid)->first();

            if (!$note) {
                return [
                    'success' => false,
                    'data' => [],
                    'message' => 'Clinical note not found',
                    'error' => 'Note not found',
                ];
            }

            $history = $this->clinicalNoteRepository->getNoteHistory($note->id);

            return [
                'success' => true,
                'data' => $history,
                'message' => 'Note history retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get note history', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve note history',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNotesByDateRange(int $patientId, string $startDate, string $endDate): array
    {
        try {
            $notes = $this->clinicalNoteRepository->getByDateRange($patientId, $startDate, $endDate);

            return [
                'success' => true,
                'data' => $notes,
                'message' => 'Notes retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get notes by date range', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve notes',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function searchNotes(string $searchTerm, ?int $facilityId = null, int $limit = 20): array
    {
        try {
            $notes = $this->clinicalNoteRepository->searchNotes($searchTerm, $facilityId, $limit);

            return [
                'success' => true,
                'data' => $notes,
                'message' => 'Search completed successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to search notes', [
                'error' => $e->getMessage(),
                'search_term' => $searchTerm,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to search notes',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNoteStatistics(int $facilityId): array
    {
        try {
            $stats = $this->clinicalNoteRepository->getNoteCountByStatus($facilityId);

            return [
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get note statistics', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestPatientNote(int $patientId): array
    {
        try {
            $note = $this->clinicalNoteRepository->getLatestNote($patientId);

            return [
                'success' => true,
                'data' => $note,
                'message' => $note ? 'Latest note retrieved successfully' : 'No notes found for patient',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get latest patient note', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve latest note',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function canAmendNote(ClinicalNote $note): bool
    {
        // Only final notes can be amended
        if (!$note->isFinal()) {
            return false;
        }

        // Don't allow amending cancelled notes
        if ($note->isCancelled()) {
            return false;
        }

        // Don't allow amending if note already has child amendments? 
        // Based on business rules - some systems allow multiple amendments
        // Here we allow amendments regardless

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function generateNotePdf(string $uuid): array
    {
        try {
            $result = $this->getNoteByUuid($uuid);
            
            if (!$result['success']) {
                return $result;
            }

            $note = $result['data'];

            // This would integrate with a PDF generation library like DomPDF, Snappy, etc.
            // For now, return a placeholder response
            // In production, generate actual PDF and return as base64 or file path

            return [
                'success' => true,
                'data' => [
                    'pdf_base64' => null, // Would be actual PDF content
                    'note_id' => $note->id,
                    'note_uuid' => $uuid,
                ],
                'message' => 'PDF generation requires PDF library integration',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate PDF', [
                'error' => $e->getMessage(),
                'uuid' => $uuid,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate clinical note data.
     *
     * @param array $data
     * @param ClinicalNote|null $note
     * @return array
     * @throws ValidationException
     */
    private function validateNoteData(array $data, ?ClinicalNote $note = null): array
    {
        $rules = [
            'facility_id' => 'required|exists:facilities,id',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'subjective' => 'nullable|string',
            'objective' => 'nullable|string',
            'assessment' => 'nullable|string',
            'plan' => 'nullable|string',
            'review_of_systems' => 'nullable|string',
            'past_medical_history' => 'nullable|string',
            'note_type' => 'nullable|in:initial,follow_up,progress,discharge,consultation',
            'note_status' => 'nullable|in:draft,final,amended,cancelled',
            'noted_at' => 'nullable|date',
            'signature' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'structured_data' => 'nullable|array',
            'parent_note_id' => 'nullable|exists:clinical_notes,id',
        ];

        // For updates, make fields sometimes required
        if ($note) {
            foreach ($rules as $field => $rule) {
                if (strpos($rule, 'required') === 0) {
                    $rules[$field] = 'sometimes|' . substr($rule, 9);
                }
            }
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}