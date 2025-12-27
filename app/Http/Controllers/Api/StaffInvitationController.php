<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffInvitation\StoreStaffInvitationRequest;
use App\Http\Requests\StaffInvitation\UpdateStaffInvitationRequest;
use App\Http\Resources\StaffInvitationResource;
use App\Models\StaffInvitation;
use App\Services\Contracts\StaffInvitationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StaffInvitationController extends Controller
{
    /**
     * The staff invitation service instance.
     */
    protected StaffInvitationServiceInterface $service;

    /**
     * Create a new controller instance.
     */
    public function __construct(StaffInvitationServiceInterface $service)
    {
        $this->service = $service;
        
        // Apply middleware
        // $this->middleware('auth:api');
        // $this->middleware('throttle:60,1')->except(['index', 'show']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'facility_id', 'staff_id', 'department_id', 'role_id', 'invited_by_staff_id', 'sent_from', 'sent_to', 'sort_by', 'sort_order']);
            $perPage = $request->input('per_page', 20);
            
            $result = $this->service->getAllInvitations($filters, $perPage);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            // Transform data using resource collection
            $resource = StaffInvitationResource::collection($result['data']);
            
            // Add pagination metadata
            $responseData = array_merge(
                ['data' => $resource],
                $result['meta'] ?? []
            );
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $responseData['data'],
                'meta' => [
                    'pagination' => [
                        'total' => $result['data']->total(),
                        'count' => $result['data']->count(),
                        'per_page' => $result['data']->perPage(),
                        'current_page' => $result['data']->currentPage(),
                        'total_pages' => $result['data']->lastPage(),
                        'links' => [
                            'first' => $result['data']->url(1),
                            'last' => $result['data']->url($result['data']->lastPage()),
                            'prev' => $result['data']->previousPageUrl(),
                            'next' => $result['data']->nextPageUrl(),
                        ],
                    ],
                    'filters_applied' => $filters,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Controller error in index method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving invitations.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffInvitationRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $invitedByStaffId = Auth::id(); // Assuming staff ID matches user ID
            
            $result = $this->service->createInvitation($validatedData, $invitedByStaffId);
            
            $statusCode = $result['success'] ? 201 : 422;
            
            if ($result['success'] && $result['data']) {
                $result['data'] = new StaffInvitationResource($result['data']);
            }
            
            return response()->json($result, $statusCode);
            
        } catch (\Exception $e) {
            Log::error('Controller error in store method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->service->getInvitationById($id);
            
            if (!$result['success']) {
                $statusCode = $result['message'] === 'Invitation not found.' ? 404 : 500;
                return response()->json($result, $statusCode);
            }
            
            $result['data'] = new StaffInvitationResource($result['data']);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            Log::error('Controller error in show method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStaffInvitationRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $result = $this->service->updateInvitation($id, $validatedData);
            
            $statusCode = $result['success'] ? 200 : 422;
            
            if ($result['success'] && $result['data']) {
                $result['data'] = new StaffInvitationResource($result['data']);
            }
            
            return response()->json($result, $statusCode);
            
        } catch (\Exception $e) {
            Log::error('Controller error in update method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->service->deleteInvitation($id);
            
            $statusCode = $result['success'] ? 200 : ($result['message'] === 'Invitation not found.' ? 404 : 422);
            
            return response()->json($result, $statusCode);
            
        } catch (\Exception $e) {
            Log::error('Controller error in destroy method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Accept a staff invitation.
     */
    public function accept(int $id): JsonResponse
    {
        try {
            $this->authorize('accept', StaffInvitation::findOrFail($id));
            
            $result = $this->service->acceptInvitation($id);
            
            $statusCode = $result['success'] ? 200 : 422;
            
            if ($result['success'] && $result['data']) {
                $result['data'] = new StaffInvitationResource($result['data']);
            }
            
            return response()->json($result, $statusCode);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to accept this invitation.',
                'errors' => ['authorization' => ['Insufficient permissions']],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller error in accept method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while accepting the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Decline a staff invitation.
     */
    public function decline(int $id): JsonResponse
    {
        try {
            $this->authorize('decline', StaffInvitation::findOrFail($id));
            
            $result = $this->service->declineInvitation($id);
            
            $statusCode = $result['success'] ? 200 : 422;
            
            if ($result['success'] && $result['data']) {
                $result['data'] = new StaffInvitationResource($result['data']);
            }
            
            return response()->json($result, $statusCode);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to decline this invitation.',
                'errors' => ['authorization' => ['Insufficient permissions']],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller error in decline method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while declining the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Resend a staff invitation.
     */
    public function resend(int $id): JsonResponse
    {
        try {
            $this->authorize('resend', StaffInvitation::findOrFail($id));
            
            $result = $this->service->resendInvitation($id);
            
            $statusCode = $result['success'] ? 200 : 422;
            
            if ($result['success'] && $result['data']) {
                $result['data'] = new StaffInvitationResource($result['data']);
            }
            
            return response()->json($result, $statusCode);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to resend this invitation.',
                'errors' => ['authorization' => ['Insufficient permissions']],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller error in resend method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while resending the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Cancel a staff invitation.
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            $this->authorize('cancel', StaffInvitation::findOrFail($id));
            
            $result = $this->service->cancelInvitation($id);
            
            $statusCode = $result['success'] ? 200 : 422;
            
            return response()->json($result, $statusCode);
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to cancel this invitation.',
                'errors' => ['authorization' => ['Insufficient permissions']],
                'data' => null
            ], 403);
        } catch (\Exception $e) {
            Log::error('Controller error in cancel method', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while cancelling the invitation.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get invitations for current staff member.
     */
    public function myInvitations(Request $request): JsonResponse
    {
        try {
            $staffId = Auth::id(); // Assuming current user is staff
            $filters = $request->only(['status', 'facility_id', 'department_id']);
            
            $result = $this->service->getInvitationsByStaff($staffId, $filters);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            $resource = StaffInvitationResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $resource,
                'meta' => $result['meta']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Controller error in myInvitations method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving your invitations.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }

    /**
     * Get pending invitations for current staff member.
     */
    public function myPendingInvitations(): JsonResponse
    {
        try {
            $staffId = Auth::id(); // Assuming current user is staff
            
            $result = $this->service->getPendingInvitationsForStaff($staffId);
            
            if (!$result['success']) {
                return response()->json($result, 500);
            }
            
            $resource = StaffInvitationResource::collection($result['data']);
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $resource,
                'meta' => $result['meta']
            ]);
            
        } catch (\Exception $e) {
            Log::error('Controller error in myPendingInvitations method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while retrieving your pending invitations.',
                'errors' => ['system' => ['Internal server error']],
                'data' => null
            ], 500);
        }
    }
}