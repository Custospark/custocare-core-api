<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffByAdminRequest;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Http\Resources\StaffSearchResource;
use App\Models\FacilityStaffRole;
use App\Models\Staff;
use App\Models\User;
use App\Services\Contracts\StaffServiceInterface;
use App\Services\User\Contracts\UserServiceInterface;
use App\Support\HealthcareIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    /**
     * Staff service instance.
     */
    protected StaffServiceInterface $staffService;
    protected UserServiceInterface $userService;

    /**
     * Create a new controller instance.
     */
    public function __construct(StaffServiceInterface $staffService,UserServiceInterface $userService)
    {
        $this->staffService = $staffService;
        $this->userService=$userService;
        
        // Apply middleware
        //TODO:Define these middlewares.
        // $this->middleware('auth:api');
        // $this->middleware('can:viewAny,App\Models\Staff')->only(['index', 'show']);
        // $this->middleware('can:create,App\Models\Staff')->only(['store']);
        // $this->middleware('can:update,staff')->only(['update']);
        // $this->middleware('can:delete,staff')->only(['destroy']);
    }

    /**
     * Display a listing of the staff for a given facility
     */
        public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'facility_id' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $filters = $request->only([
                'employment_status',
                'global_role_level',
                'search',
                'has_expired_license',
            ]);

            $facilityId = (int) $validated['facility_id'];
            $perPage    = (int) $request->get('per_page', 20);

            $staff = $this->staffService
                ->getAllStaff($filters) // ✅ Builder
                ->join(
                    'facility_staff_roles',
                    'facility_staff_roles.staff_id',
                    '=',
                    'staff.id'
                )
                ->where('facility_staff_roles.facility_id', $facilityId)
                ->select('staff.*') // ✅ prevent column collisions
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully.',
                'data'    => StaffResource::collection($staff),
                'meta'    => [
                    'current_page' => $staff->currentPage(),
                    'last_page'    => $staff->lastPage(),
                    'per_page'     => $staff->perPage(),
                    'total'        => $staff->total(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving staff list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff list.',
                'data'    => [],
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

 

public function staffSearch(Request $request): JsonResponse
{
    try {
        // $this->authorize('viewAny', Staff::class);

        $criteria = $request->validate([
            'q' => 'nullable|string|max:120', // general search: staff_uuid OR name OR employee_id
            'staff_uuid' => 'nullable|string',
            'employment_status' => 'nullable|in:employed,suspended,unemployed,terminated,retired,credentialing_pending',
            'global_role_level' => 'nullable|in:super_admin,facility_admin,department_head,attending_physician,fellow,resident,nurse_practitioner,physician_assistant,registered_nurse,licensed_practical_nurse,pharmacist,therapist,technician,support_staff',
            'accepts_new_patients' => 'nullable|boolean',
            'facility_id' => 'nullable|integer|exists:facilities,id', // optional, if scoping via access table
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = (int) ($criteria['limit'] ?? 15);
        $q = $criteria['q'] ?? null;

        $staff = Staff::query()
            ->with(['user:id,global_user_uuid,first_name,last_name,display_name'])
            ->select([
                'id',
                'staff_uuid',
                'user_id',
                'employee_id',
                'professional_title',
                'global_role_level',
                'employment_status',
                'accepts_new_patients',
                'max_concurrent_patients',
                'license_expiry_date',
                'created_at',
            ])
            ->when(!empty($criteria['staff_uuid']), fn ($query) =>
                $query->where('staff_uuid', $criteria['staff_uuid'])
            )
            ->when(!empty($criteria['employment_status']), fn ($query) =>
                $query->where('employment_status', $criteria['employment_status'])
            )
            ->when(!empty($criteria['global_role_level']), fn ($query) =>
                $query->where('global_role_level', $criteria['global_role_level'])
            )
            ->when(isset($criteria['accepts_new_patients']), fn ($query) =>
                $query->where('accepts_new_patients', (bool)$criteria['accepts_new_patients'])
            )
            ->when($q, function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    // Staff Number / Employee ID partial match
                    $inner->where('staff_uuid', 'like', "%{$q}%")
                          ->orWhere('employee_id', 'like', "%{$q}%")
                          // User name search
                          ->orWhereHas('user', function ($u) use ($q) {
                              $u->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%")
                                ->orWhere('display_name', 'like', "%{$q}%");
                          });
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => StaffSearchResource::collection($staff),
            'meta' => [
                'total' => $staff->count(),
                'criteria' => $criteria,
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to staffSearch', [
            'criteria' => $request->all(),
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to search staff.',
            'data' => [],
        ], 500);
    }
}


   /**
    * Record of all medical Professionals.
    */
     public function getAllMedicalProfesionalRecords(Request $request): JsonResponse
{
    try {
        // Get filters from request
        $filters = $request->only([
            'employment_status',
            'global_role_level',
            'search',
            'has_expired_license'
        ]);

        // Start the query directly on the Staff model
        $query = Staff::query();

        // Apply filters if provided
        if (!empty($filters['employment_status'])) {
            $query->where('employment_status', $filters['employment_status']);
        }

        if (!empty($filters['global_role_level'])) {
            $query->where('global_role_level', $filters['global_role_level']);
        }

        if (!empty($filters['has_expired_license'])) {
            // Assuming has_expired_license is boolean
            $query->where('has_expired_license', $filters['has_expired_license']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $staff = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Staff retrieved successfully.',
            'data' => StaffResource::collection($staff),
            'meta' => [
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
                'per_page' => $staff->perPage(),
                'total' => $staff->total(),
            ]
        ]);
    } catch (\Exception $e) {
        Log::error('Error retrieving staff list', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve staff list.',
            'data' => []
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}



    /**
     * Store a newly created staff in storage.
     */
    public function store(StoreStaffRequest $request): JsonResponse
    {
        try {
            $staff = $this->staffService->createStaff(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Staff account created successfully.',
                'data'    => new StaffResource($staff),
                'errors'  => null,
            ], JsonResponse::HTTP_CREATED);

        } catch (\Illuminate\Auth\AuthenticationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_UNAUTHORIZED);

        } catch (\RuntimeException $e) {

            Log::warning('Staff creation failed', [
                'reason' => $e->getMessage(),
                'input'  => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create staff record.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {

            Log::error('Unexpected staff creation error', [
                'exception' => $e,
                'input'     => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function createStaffByAdmin(StoreStaffByAdminRequest $request): JsonResponse
    {
    //    Log::info($request);
           $first_name = $request->input('first_name');
           $last_name = $request->input('last_name');
           $email = $request->input('email');
           $phone = $request->input('phone');
           $password=$request->input('password');
           if(!$password){
            $password=HealthcareIdGenerator::generateRandomCode();
           }
           $emailHash = hash('sha256', $email);
           $userRegistrationData=[
            'first_name'=>$first_name,
            'last_name'=>$last_name,
            'email_hash'=>$emailHash,
            'phone'=>$phone,
            'password'=>$password,
            'global_user_uuid'=>HealthcareIdGenerator::generateRandomCode(),
           ];

      Log::info($userRegistrationData);
       $user=User::where('email_hash',$emailHash);
       if($user){
        Log::info("New User created!.");
        $user=User::create($userRegistrationData);
       }
       Log::warning("User Existed.");
       dd("Wait");
    //    Log::alert($user);
    //    dd("Hold on");
        try {
            $staff = $this->staffService->createStaff(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Staff account created successfully.',
                'data'    => new StaffResource($staff),
                'errors'  => null,
                'credentials'=>[
                    'password'=>$password,
                     'email'=>$email,

                ]
            ], JsonResponse::HTTP_CREATED);

        } catch (\Illuminate\Auth\AuthenticationException $e) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_UNAUTHORIZED);

        } catch (\RuntimeException $e) {

            Log::warning('Staff creation failed', [
                'reason' => $e->getMessage(),
                'input'  => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create staff record.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_BAD_REQUEST);

        } catch (\Throwable $e) {

            Log::error('Unexpected staff creation error', [
                'exception' => $e,
                'input'     => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error.',
                'data'    => null,
                'errors'  => null,
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified staff.
     */
    public function show(int $id): JsonResponse
    {
    Log::info($id);
    try {
            // Get staff by ID
            $staff = $this->staffService->getStaffById($id);
            Log::info($staff);
            
            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff not found.',
                    'data' => null
                ], JsonResponse::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Staff retrieved successfully.',
                'data' => new StaffResource($staff)
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving staff', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve staff.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified staff in storage.
     */
    public function update(UpdateStaffRequest $request, int $id): JsonResponse
    {
        try {
            // Get validated data
            $data = $request->validated();
            
            // Update staff
            $result = $this->staffService->updateStaff($id, $data);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new StaffResource($result['data'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating staff', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while updating staff.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified staff from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Delete staff
            $result = $this->staffService->deleteStaff($id);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting staff', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while deleting staff.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update staff license information.
     */
    public function updateLicense(Request $request, int $id): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'license_number_encrypted' => 'required|string|max:512',
                'license_number_hash' => 'required|string|max:128',
                'issuing_state' => 'required|string|max:50',
                'expiry_date' => 'required|date|after:today',
            ]);
            
            // Update license
            $result = $this->staffService->updateLicenseInfo($id, $validated);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new StaffResource($result['data'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error updating staff license', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update license information.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update staff employment status.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'status' => 'required|in:active,on_leave,suspended,terminated,retired,credentialing_pending',
                'reason' => 'nullable|string|max:1000',
            ]);
            
            // Check authorization
            $staff = $this->staffService->getStaffById($id);
            if ($staff && !auth::user()->can('updateEmploymentStatus', $staff)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update employment status.',
                    'data' => null
                ], JsonResponse::HTTP_FORBIDDEN);
            }
            
            // Update status
            $result = $this->staffService->updateEmploymentStatus(
                $id, 
                $validated['status'], 
                $validated['reason'] ?? null
            );
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => new StaffResource($result['data'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error updating staff status', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment status.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get staff with expiring credentials.
     */
    public function expiringCredentials(Request $request): JsonResponse
    {
        try {
            // Check authorization
            if (!auth::user()->can('viewAny', \App\Models\Staff::class)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this information.',
                    'data' => null
                ], JsonResponse::HTTP_FORBIDDEN);
            }
            
            $days = $request->get('days', 30);
            $result = $this->staffService->getStaffWithExpiringCredentials($days);
            
            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
            
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data']
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting expiring credentials', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve expiring credentials.',
                'data' => []
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate staff action authorization.
     */
    public function validateAction(Request $request, int $id): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'action' => 'required|string|in:prescribe_medication,supervise_others,access_confidential',
            ]);
            
            $result = $this->staffService->validateStaffAction($id, $validated['action']);
            
            return response()->json([
                'success' => $result['valid'],
                'message' => $result['message'],
                'data' => [
                    'valid' => $result['valid'],
                    'errors' => $result['errors']
                ]
            ], $result['valid'] ? JsonResponse::HTTP_OK : JsonResponse::HTTP_FORBIDDEN);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Error validating staff action', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate staff action.',
                'data' => null
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}