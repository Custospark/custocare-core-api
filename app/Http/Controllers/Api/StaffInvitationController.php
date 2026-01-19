<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StaffInvitation\StoreStaffInvitationRequest;
use App\Http\Requests\StaffInvitation\UpdateStaffInvitationRequest;
use App\Http\Resources\StaffInvitationResource;
use App\Http\Resources\FacilityStaffAssignmentResource;
use App\Http\Resources\FacilityStaffRoleResource;
use App\Models\Staff;
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
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'facility_id', 'staff_id', 'department_id', 'role_code', 'module_code', 'invited_by_staff_id', 'sent_from', 'sent_to', 'sort_by', 'sort_order']);
            $perPage = $request->input('per_page', 20);
            
            $invitations = $this->service->getAllInvitations($filters, $perPage);
            
            return $this->successResponse(
                StaffInvitationResource::collection($invitations),
                'Invitations retrieved successfully.',
                [
                    'filters_applied' => $filters,
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve invitations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve invitations.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStaffInvitationRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            Log::info($validatedData);
            $invitation = $this->service->createInvitation($validatedData);
            
            return $this->successResponse(
                new StaffInvitationResource($invitation),
                'Invitation created and sent successfully.',
                null,
                201
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to create invitation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            $statusCode = $this->getStatusCodeFromException($e);
            
            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $invitation = $this->service->getInvitationById($id);
            
            if (!$invitation) {
                return $this->errorResponse(
                    'Invitation not found.',
                    404,
                    ['id' => 'The specified invitation does not exist.']
                );
            }
            
            return $this->successResponse(
                new StaffInvitationResource($invitation),
                'Invitation retrieved successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve invitation.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStaffInvitationRequest $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            
            $invitation = $this->service->updateInvitation($id, $validatedData);
            
            return $this->successResponse(
                new StaffInvitationResource($invitation),
                'Invitation updated successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to update invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            $statusCode = $this->getStatusCodeFromException($e);
            
            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteInvitation($id);
            
            return $this->successResponse(
                null,
                'Invitation deleted successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to delete invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $statusCode = $this->getStatusCodeFromException($e);
            
            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

  public function accept(int $id): JsonResponse
    {
        try {
            // Authorize the action
            $invitation = StaffInvitation::findOrFail($id);
            // $this->authorize('accept', $invitation);
            
            // Accept invitation (atomic transaction)
            $result = $this->service->acceptInvitation($id);
            
            $message = $result['was_existing'] ?? false
                ? 'Invitation accepted. You already have an active assignment at this facility.'
                : 'Invitation accepted successfully. Staff assignment created.';
            
            return $this->successResponse(
                [
                    'invitation' => new StaffInvitationResource($result['invitation']),
                    'assignment' => new FacilityStaffRoleResource($result['assignment']),
                    'was_existing_assignment' => $result['was_existing'] ?? false,
                ],
                $message
            );
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse(
                'You are not authorized to accept this invitation.',
                403,
                ['authorization' => 'Insufficient permissions.']
            );
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse(
                'Invitation not found.',
                404,
                ['invitation' => ['The specified invitation does not exist.']]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to accept invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                400,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Decline an invitation
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function decline(int $id): JsonResponse
    {
        try {
            // Authorize the action
            $invitation = StaffInvitation::findOrFail($id);
            // $this->authorize('decline', $invitation);
            
            // Decline invitation
            $result = $this->service->declineInvitation($id);
            
            return $this->successResponse(
                [
                    'invitation' => new StaffInvitationResource($result),
                ],
                'Invitation declined successfully.'
            );
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse(
                'You are not authorized to decline this invitation.',
                403,
                ['authorization' => 'Insufficient permissions.']
            );
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse(
                'Invitation not found.',
                404,
                ['invitation' => ['The specified invitation does not exist.']]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to decline invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                $e->getMessage(),
                400,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

  

    /**
     * Resend a staff invitation.
     */
    public function resend(int $id): JsonResponse
    {
        try {
            // $this->authorize('resend', StaffInvitation::findOrFail($id));
            
            $invitation = $this->service->resendInvitation($id);
            
            return $this->successResponse(
                new StaffInvitationResource($invitation),
                'Invitation resent successfully.'
            );
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse(
                'You are not authorized to resend this invitation.',
                403,
                ['authorization' => 'Insufficient permissions.']
            );
        } catch (\Exception $e) {
            Log::error('Failed to resend invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $statusCode = $this->getStatusCodeFromException($e);
            
            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Cancel a staff invitation.
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            // $this->authorize('cancel', StaffInvitation::findOrFail($id));
            
            $this->service->cancelInvitation($id);
            
            return $this->successResponse(
                null,
                'Invitation cancelled successfully.'
            );
            
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->errorResponse(
                'You are not authorized to cancel this invitation.',
                403,
                ['authorization' => 'Insufficient permissions.']
            );
        } catch (\Exception $e) {
            Log::error('Failed to cancel invitation', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $statusCode = $this->getStatusCodeFromException($e);
            
            return $this->errorResponse(
                $e->getMessage(),
                $statusCode,
                ['invitation' => [$e->getMessage()]]
            );
        }
    }

    /**
     * Get invitations for current staff member.
     */
    public function myInvitations(Request $request): JsonResponse
    {
        try {
            $staffId = Auth::id();
            $filters = $request->only(['status', 'facility_id', 'department_id']);
            
            $invitations = $this->service->getInvitationsByStaff($staffId, $filters);
            
            return $this->successResponse(
                StaffInvitationResource::collection($invitations),
                'Your invitations retrieved successfully.',
                [
                    'total' => count($invitations),
                    'staff_id' => $staffId
                ]
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve user invitations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve your invitations.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Get pending invitations for current staff member.
     */
    public function myPendingInvitations(): JsonResponse
    {
        try {
            $userId = Auth::id();

            $staffId = Staff::where('user_id', $userId)->value('id');

            if (!$staffId) {
                return $this->errorResponse(
                    $userId,
                    403
                );
            }

            $invitations = $this->service
                ->getPendingInvitationsForStaff($staffId);

            return $this->successResponse(
                StaffInvitationResource::collection($invitations),
                'Your pending invitations retrieved successfully.',
                [
                    'total'   => $invitations->count(),
                    'staff_id'=> $staffId,
                ]
            );

        } catch (\Throwable $e) {
            Log::error('Failed to retrieve user pending invitations', [
                'user_id' => Auth::id(),
                'error'   => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Failed to retrieve your pending invitations.',
                500
            );
        }
    }


    /**
     * Show invitation by UUID (public endpoint).
     */
    public function showByUuid(string $uuid): JsonResponse
    {
        try {
            $invitation = $this->service->getInvitationByUuid($uuid);
            
            if (!$invitation) {
                return $this->errorResponse(
                    'Invitation not found.',
                    404,
                    ['uuid' => 'The specified invitation does not exist.']
                );
            }
            
            return $this->successResponse(
                new StaffInvitationResource($invitation),
                'Invitation retrieved successfully.'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to retrieve invitation by UUID', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                'Failed to retrieve invitation.',
                500,
                ['system' => 'An unexpected error occurred.']
            );
        }
    }

    /**
     * Helper: Send success response with consistent format.
     */
    protected function successResponse($data = null, string $message = 'Success', $meta = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    /**
     * Helper: Send error response with consistent format.
     */
    protected function errorResponse(string $message, int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'data' => null,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Helper: Determine HTTP status code from exception message.
     */
    protected function getStatusCodeFromException(\Exception $e): int
    {
        $message = strtolower($e->getMessage());
        
        if (str_contains($message, 'not found')) {
            return 404;
        }
        
        if (str_contains($message, 'duplicate') || 
            str_contains($message, 'already exists') ||
            str_contains($message, 'cannot update') ||
            str_contains($message, 'cannot delete')) {
            return 422;
        }
        
        if (str_contains($message, 'expired')) {
            return 410; // Gone
        }
        
        return 500;
    }
}