<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalDocument\StoreClinicalDocumentRequest;
use App\Http\Requests\ClinicalDocument\UpdateClinicalDocumentRequest;
use App\Http\Resources\ClinicalDocumentResource;
use App\Http\Resources\ClinicalDocumentCollection;
use App\Services\Contracts\ClinicalDocumentServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClinicalDocumentController extends Controller
{
    /**
     * @var ClinicalDocumentServiceInterface
     */
    protected $clinicalDocumentService;

    /**
     * Constructor
     *
     * @param ClinicalDocumentServiceInterface $clinicalDocumentService
     */
    public function __construct(ClinicalDocumentServiceInterface $clinicalDocumentService)
    {
        $this->clinicalDocumentService = $clinicalDocumentService;
    }

    /**
     * Display a listing of clinical documents.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'patient_id',
                'facility_id',
                'visit_id',
                'document_type',
                'status',
                'date_from',
                'date_to',
                'search'
            ]);
            
            $perPage = $request->get('per_page', 20);
            
            $result = $this->clinicalDocumentService->getAllDocuments($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            
            $documents = $result['data'];
            
            return response()->json(new ClinicalDocumentCollection(
                ClinicalDocumentResource::collection($documents)
                    ->additional(['meta' => [
                        'total' => $documents->total(),
                        'per_page' => $documents->perPage(),
                        'current_page' => $documents->currentPage(),
                    ]])
            ));
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created clinical document.
     *
     * @param StoreClinicalDocumentRequest $request
     * @return JsonResponse
     */
    public function store(StoreClinicalDocumentRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $file = $request->file('document_file');
            
            $result = $this->clinicalDocumentService->createDocument($data, $file);
            
            if (!$result['success']) {
                $statusCode = match(true) {
                    str_contains($result['message'], 'not found') => JsonResponse::HTTP_NOT_FOUND,
                    str_contains($result['message'], 'already been uploaded') => JsonResponse::HTTP_CONFLICT,
                    default => JsonResponse::HTTP_BAD_REQUEST
                };
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'data' => new ClinicalDocumentResource($result['data']),
                'message' => $result['message']
            ], JsonResponse::HTTP_CREATED);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@store', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create clinical document. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified clinical document.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->clinicalDocumentService->getDocumentById($id);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => new ClinicalDocumentResource($result['data']),
                'message' => $result['message']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@show', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve clinical document. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified clinical document.
     *
     * @param UpdateClinicalDocumentRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateClinicalDocumentRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $result = $this->clinicalDocumentService->updateDocument($id, $data);
            
            if (!$result['success']) {
                $statusCode = str_contains($result['message'], 'not found') 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json([
                'success' => true,
                'data' => new ClinicalDocumentResource($result['data']),
                'message' => $result['message']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@update', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update clinical document. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified clinical document.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->clinicalDocumentService->deleteDocument($id);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => $result['message']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@destroy', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete clinical document. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get documents by patient ID.
     *
     * @param Request $request
     * @param int $patientId
     * @return JsonResponse
     */
    public function byPatient(Request $request, int $patientId): JsonResponse
    {
        try {
            $filters = $request->only(['document_type', 'status', 'visit_id']);
            $perPage = $request->get('per_page', 20);
            
            $result = $this->clinicalDocumentService->getDocumentsByPatient($patientId, $filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            
            $documents = $result['data'];
            
            return response()->json(new ClinicalDocumentCollection(
                ClinicalDocumentResource::collection($documents)
                    ->additional(['meta' => [
                        'patient_id' => $patientId,
                        'total' => $documents->total(),
                        'per_page' => $documents->perPage(),
                        'current_page' => $documents->currentPage(),
                    ]])
            ));
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@byPatient', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient documents. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download clinical document file.
     *
     * @param int $id
     * @return JsonResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(int $id)
    {
        try {
            $result = $this->clinicalDocumentService->downloadDocument($id);
            
            if (!$result['success']) {
                $statusCode = str_contains($result['message'], 'not found') 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            $filePath = storage_path('app/' . $result['data']['file_path']);
            $fileName = $result['data']['file_name'];
            
            return response()->download($filePath, $fileName, [
                'Content-Type' => $result['data']['mime_type'],
                'Content-Length' => $result['data']['size'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@download', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to download document. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Verify document integrity.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function verifyIntegrity(int $id): JsonResponse
    {
        try {
            $result = $this->clinicalDocumentService->verifyDocumentIntegrity($id);
            
            if (!$result['success']) {
                $statusCode = str_contains($result['message'], 'not found') 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@verifyIntegrity', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify document integrity. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update document status.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:' . implode(',', \App\Models\ClinicalDocument::getValidStatuses()),
            ]);
            
            $status = $request->input('status');
            
            $result = $this->clinicalDocumentService->updateDocumentStatus($id, $status);
            
            if (!$result['success']) {
                $statusCode = str_contains($result['message'], 'not found') 
                    ? JsonResponse::HTTP_NOT_FOUND 
                    : JsonResponse::HTTP_BAD_REQUEST;
                
                return response()->json($result, $statusCode);
            }
            
            return response()->json($result);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@updateStatus', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update document status. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get document statistics.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $facilityId = $request->get('facility_id');
            
            $result = $this->clinicalDocumentService->getStatistics($facilityId);
            
            if (!$result['success']) {
                return response()->json($result, JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
            }
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Error in ClinicalDocumentController@statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics. Please try again later.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}