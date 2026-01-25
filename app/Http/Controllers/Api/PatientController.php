<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PatientCreationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientSearchResource;
use App\Models\OnboardingToken;
use App\Services\Contracts\PatientServiceInterface;
use App\Models\Patient;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use App\Support\HealthcareIdGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PatientController extends Controller
{
    /**
     * @var PatientServiceInterface
     */
    private $patientService;

    /**
     * Constructor.
     */
    public function __construct(PatientServiceInterface $patientService)
    {
        $this->patientService = $patientService;
        
        // Apply policy middleware
        // $this->authorizeResource(Patient::class, 'patient');
    }

    /**
     * Display a listing of patients.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $patients = $this->patientService->getAllPatients($perPage);
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'current_page' => $patients->currentPage(),
                    'last_page' => $patients->lastPage(),
                    'per_page' => $patients->perPage(),
                    'total' => $patients->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patients list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients.',
                'errors' => config('app.debug') ? ['details' => $e->getMessage()] : [],
            ], 500);
        }
    }


    /**
         * Search patients (essential info only).
         * Search can be by patient_uuid (patient number), name, status, sex, DOB range, etc.
         * Returns a lean payload for UI search/autocomplete.
         */
        public function patientSearch(Request $request): JsonResponse
        {
            try {
                // $this->authorize('viewAny', Patient::class);

                $criteria = $request->validate([
                    'q' => 'nullable|string|max:120', // general search term: patient_uuid OR name
                    'patient_uuid' => 'nullable|string',
                    'status' => 'nullable|in:active,inactive,deceased,merged,test_patient,system_patient',
                    'biological_sex' => 'nullable|in:male,female,intersex,unknown',
                    'date_of_birth_from' => 'nullable|date',
                    'date_of_birth_to' => 'nullable|date|after_or_equal:date_of_birth_from',
                    'facility_id' => 'nullable|integer|exists:facilities,id', // optional, if you track by facility elsewhere
                    'limit' => 'nullable|integer|min:1|max:50',
                ]);

                $limit = (int) ($criteria['limit'] ?? 15);
                $q = $criteria['q'] ?? null;

                $patients = Patient::query()
                    ->with(['user:id,global_user_uuid,first_name,last_name,display_name,phone_hash,email_hash'])
                    ->select([
                        'id',
                        'patient_uuid',
                        'user_id',
                        'date_of_birth',
                        'biological_sex',
                        'blood_type',
                        'status',
                        'requires_isolation',
                        'created_at',
                    ])
                    ->when(!empty($criteria['patient_uuid']), function ($query) use ($criteria) {
                        $query->where('patient_uuid', $criteria['patient_uuid']);
                    })
                    ->when(!empty($criteria['status']), fn ($query) => $query->where('status', $criteria['status']))
                    ->when(!empty($criteria['biological_sex']), fn ($query) => $query->where('biological_sex', $criteria['biological_sex']))
                    ->when(!empty($criteria['date_of_birth_from']), fn ($query) => $query->whereDate('date_of_birth', '>=', $criteria['date_of_birth_from']))
                    ->when(!empty($criteria['date_of_birth_to']), fn ($query) => $query->whereDate('date_of_birth', '<=', $criteria['date_of_birth_to']))
                    ->when($q, function ($query) use ($q) {
                        $query->where(function ($inner) use ($q) {
                            // Patient Number (patient_uuid) partial match
                            $inner->where('patient_uuid', 'like', "%{$q}%")
                                // User name search (safe basic fields)
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
                    'data' => PatientSearchResource::collection($patients),
                    'meta' => [
                        'total' => $patients->count(),
                        'criteria' => $criteria,
                    ],
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to patientSearch', [
                    'criteria' => $request->all(),
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search patients.',
                    'data' => [],
                ], 500);
            }
        }

   
   
    /**
     * Staff creates a patient + user account in one go (atomic).
     * Returns lean PatientSearchResource for immediate UI use.
     */
    public function createPatientByStaff(Request $request): JsonResponse
    {
        try {
            // $this->authorize('create', Patient::class);

            $data = $request->validate([
                // USER minimal
                'first_name' => 'required|string|max:100',
                'last_name'  => 'required|string|max:100',
                'email'      => 'nullable|email|max:255',
                'phone'      => 'nullable|string|max:30',

                // PATIENT minimal
                'date_of_birth'  => 'required|date',
                'biological_sex' => 'required|in:male,female,intersex,unknown',

                // Optional context
                'created_from_facility_id' => 'nullable|integer|exists:facilities,id',

                // Duplicate-handling controls (driven by UI)
                'action_on_possible_duplicate' => 'nullable|in:block,allow',
                'existing_user_action' => 'nullable|in:use_existing,block',
            ]);

            if (empty($data['email']) && empty($data['phone'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provide at least email or phone.',
                    'errors'  => ['contact' => ['Email or phone is required.']],
                    'data'    => [],
                ], 422);
            }

            // Resolve current staff (required for visit assignment + auditing)
            $staffId = Staff::where('user_id', Auth::id())->value('id');
            if (!$staffId) {
                abort(403, 'Authenticated user is not linked to a staff record.');
            }

            $result = DB::transaction(function () use ($data, $request, $staffId) {

                // -------------------- NORMALIZE CONTACT --------------------
                $email = isset($data['email']) ? strtolower(trim($data['email'])) : null;
                $phone = isset($data['phone']) ? preg_replace('/\s+/', '', $data['phone']) : null;

                $emailHash = $email ? hash('sha256', $email) : null;
                $phoneHash = $phone ? hash('sha256', $phone) : null;

                $emailEncrypted = $email ? Crypt::encryptString($email) : null;
                $phoneEncrypted = $phone ? Crypt::encryptString($phone) : null;

                // -------------------- 1) FIND EXISTING USER (EXACT MATCH) --------------------
                $user = User::query()
                    ->where(function ($q) use ($emailHash, $phoneHash) {
                        if ($emailHash) $q->where('email_hash', $emailHash);
                        if ($phoneHash) $q->orWhere('phone_hash', $phoneHash);
                    })
                    ->lockForUpdate()
                    ->first();

                // Existing user found; allow UI-driven action
                if ($user && ($data['existing_user_action'] ?? 'use_existing') === 'block') {
                    $existingPatient = Patient::query()
                        ->where('user_id', $user->id)
                        ->with('user')
                        ->first();

                    return [
                        'status' => $existingPatient ? 'already_has_patient' : 'existing_user_found',
                        'patient' => $existingPatient,
                        'existing_user' => $user,
                        'possible_duplicate' => null,
                        'created_new_user' => false,
                        'onboarding_link_required' => false,
                        'visit' => null,
                    ];
                }

                // -------------------- 2) POSSIBLE DUPLICATE CHECK --------------------
                $possibleDuplicatePatient = null;

                if (!$user) {
                    $possibleDuplicatePatient = Patient::query()
                        ->whereDate('date_of_birth', $data['date_of_birth'])
                        ->where('biological_sex', $data['biological_sex'])
                        ->whereHas('user', function ($u) use ($data) {
                            $u->where('first_name', $data['first_name'])
                            ->where('last_name', $data['last_name']);
                        })
                        ->with('user')
                        ->first();

                    if ($possibleDuplicatePatient && ($data['action_on_possible_duplicate'] ?? 'block') === 'block') {
                        return [
                            'status' => 'possible_duplicate',
                            'patient' => null,
                            'existing_user' => null,
                            'possible_duplicate' => $possibleDuplicatePatient,
                            'created_new_user' => false,
                            'onboarding_link_required' => false,
                            'visit' => null,
                        ];
                    }
                }

                // -------------------- 3) CREATE OR UPDATE USER --------------------
                $createdNewUser = false;

                if (!$user) {
                    $user = User::create([
                        'global_user_uuid' => (string) Str::uuid(),

                        'first_name' => $data['first_name'],
                        'last_name'  => $data['last_name'],
                        'display_name' => trim($data['first_name'] . ' ' . $data['last_name']),

                        'email_encrypted' => $emailEncrypted,
                        'email_hash'      => $emailHash,
                        'phone_encrypted' => $phoneEncrypted,
                        'phone_hash'      => $phoneHash,

                        'password_hash' => null,
                        'requires_password_change' => true,

                        'created_from_facility_id' => $data['created_from_facility_id'] ?? null,
                        // if your users.created_by_staff_id expects staff_id, keep $staffId
                        'created_by_staff_id' => $staffId,
                        'created_ip' => $request->ip(),
                    ]);

                    $createdNewUser = true;
                } else {
                    $dirty = false;

                    if ($emailHash && empty($user->email_hash)) {
                        $user->email_hash = $emailHash;
                        $user->email_encrypted = $emailEncrypted;
                        $dirty = true;
                    }

                    if ($phoneHash && empty($user->phone_hash)) {
                        $user->phone_hash = $phoneHash;
                        $user->phone_encrypted = $phoneEncrypted;
                        $dirty = true;
                    }

                    if ($dirty) {
                        // if users.updated_by_staff_id expects staff_id, use $staffId
                        $user->updated_by_staff_id = $staffId;
                        $user->save();
                    }
                }

                // -------------------- 4) IF PATIENT ALREADY EXISTS, RETURN IT --------------------
                $existingPatient = Patient::query()
                    ->where('user_id', $user->id)
                    ->with('user')
                    ->first();

                if ($existingPatient) {
                    return [
                        'status' => 'already_has_patient',
                        'patient' => $existingPatient,
                        'existing_user' => $user,
                        'possible_duplicate' => $possibleDuplicatePatient,
                        'created_new_user' => $createdNewUser,
                        'onboarding_link_required' => false,
                        'visit' => null,
                    ];
                }

                // -------------------- 5) CREATE PATIENT --------------------
                $patientUuid = HealthcareIdGenerator::generate('patient');

                $mrnPlain = 'MRN-' . strtoupper(Str::random(10));
                $mrnHash  = hash('sha256', $mrnPlain);
                $mrnEncrypted = Crypt::encryptString($mrnPlain);

                $patient = Patient::create([
                    'patient_uuid' => $patientUuid,
                    'user_id' => $user->id,

                    'medical_record_number_hash' => $mrnHash,
                    'medical_record_number_encrypted' => $mrnEncrypted,

                    'date_of_birth' => $data['date_of_birth'],
                    'biological_sex' => $data['biological_sex'],

                    'status' => 'active',
                    'portal_access_enabled' => true,

                    // if patients.created_by_staff_id expects staff_id, use $staffId
                    'created_by_staff_id' => $staffId,
                ])->load('user');

                // -------------------- 6) CREATE VISIT (ASSIGN TO CURRENT STAFF) --------------------
                $visitMeta = null;

                $facilityId = $data['created_from_facility_id'] ?? null;
                if ($facilityId) {
                    $visit = Visit::create([
                        'visit_uuid' => (string) Str::uuid(),
                        'facility_id' => $facilityId,
                        'patient_id' => $patient->id,

                        // ✅ assign visit to current staff
                        'assigned_staff_id' => $staffId,
                        'assigned_at' => now(),

                        // minimal required visit fields
                        'visit_type' => 'outpatient',
                        'acuity_score' => 3,
                        'chief_complaints' => ['Registration / record creation'],
                        'arrived_at' => now(),
                        'waiting_since' => now(),

                        // sane defaults aligned to your schema
                        'is_walk_in' => true,
                        'status' => 'active',
                        'current_phase' => 'registration',

                        // audit
                        'created_by_staff_id' => $staffId,
                        'updated_by_staff_id' => $staffId,
                    ]);

                    // Return only a few fields (safe for legacy)
                    $visitMeta = [
                        'id' => $visit->id,
                        'visit_uuid' => $visit->visit_uuid,
                        'facility_id' => $visit->facility_id,
                        'patient_id' => $visit->patient_id,
                        'assigned_staff_id' => $visit->assigned_staff_id,
                        'current_phase' => $visit->current_phase,
                        'status' => $visit->status,
                        'arrived_at' => optional($visit->arrived_at)->toISOString(),
                    ];
                }

                // -------------------- 7) CREATE ONBOARDING TOKEN (HASH ONLY) --------------------
                $rawToken = Str::random(64);

                OnboardingToken::create([
                    'user_id' => $user->id,
                    'token_hash' => hash('sha256', $rawToken),
                    'expires_at' => now()->addMinutes(30),
                    'channel' => $email ? 'email' : 'sms',
                    'created_by_staff_id' => $staffId,
                    'created_ip' => $request->ip(),
                ]);

                return [
                    'status' => 'created',
                    'patient' => $patient,
                    'existing_user' => $user,
                    'possible_duplicate' => $possibleDuplicatePatient,
                    'created_new_user' => $createdNewUser,
                    'onboarding_link_required' => true,
                    'visit' => $visitMeta,
                    // 'raw_token' => $rawToken, // do NOT return in production
                ];
            });

            // -------------------- RESPONSE SHAPING (LEGACY SAFE) --------------------
            if ($result['status'] === 'possible_duplicate') {
                return response()->json([
                    'success' => false,
                    'message' => 'Possible duplicate patient found. Confirm action to proceed.',
                    'data' => [],
                    'meta' => [
                        'status' => 'possible_duplicate',
                        'possible_duplicate' => new PatientSearchResource($result['possible_duplicate']),
                    ],
                ], 409);
            }

            if ($result['status'] === 'existing_user_found') {
                return response()->json([
                    'success' => false,
                    'message' => 'A user with the same email/phone already exists. Confirm action to proceed.',
                    'data' => [],
                    'meta' => [
                        'status' => 'existing_user_found',
                        'existing_user_global_user_uuid' => $result['existing_user']->global_user_uuid,
                    ],
                ], 409);
            }

            if ($result['status'] === 'already_has_patient') {
                return response()->json([
                    'success' => true,
                    'message' => 'User already has a patient record.',
                    'data' => new PatientSearchResource($result['patient']),
                    'meta' => [
                        'status' => 'already_has_patient',
                        'possible_duplicate' => $result['possible_duplicate']
                            ? new PatientSearchResource($result['possible_duplicate'])
                            : null,
                        // Keep legacy stable; visit is null here
                        'visit' => null,
                    ],
                ], 200);
            }

            // ✅ Legacy "data" unchanged; only add visit info into meta
            return response()->json([
                'success' => true,
                'message' => 'Patient created successfully by staff.',
                'data' => new PatientSearchResource($result['patient']),
                'meta' => [
                    'status' => 'created',
                    'created_new_user' => $result['created_new_user'],
                    'possible_duplicate' => $result['possible_duplicate']
                        ? new PatientSearchResource($result['possible_duplicate'])
                        : null,
                    'onboarding_link_required' => $result['onboarding_link_required'],
                    'visit' => $result['visit'], // 👈 minimal visit payload
                ],
            ], 201);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('createPatientByStaff DB constraint error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'A conflicting patient/user record already exists.',
                'data' => [],
            ], 409);

        } catch (\Exception $e) {
            Log::error('Failed to createPatientByStaff', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create patient.',
                'data' => [],
            ], 500);
        }
    }





    /**
     * Store a newly created patient in storage.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $patient = $this->patientService->createPatient($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Patient created successfully.',
                'data' => new PatientResource($patient),
            ], 201);

        } catch (PatientCreationException $e) {
            // Meaningful client response
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => [],
                'data'=>[],
            ], $e->status);
        } catch (\Throwable $e) {
            // Unexpected errors
            Log::error('Unexpected error in patient store', [
                'data' => $request->except([
                    'medical_record_number_encrypted',
                    'primary_insurance_id_encrypted'
                ]),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create patient.',
                'errors' => ['server' => config('app.debug') ? $e->getMessage() : 'Internal server error'],
                 'data'=>[],
            ], 500);
        }
    }

    public function consumeOnboardingToken(Request $request): JsonResponse
{
    try {
        $data = $request->validate([
            'token' => 'required|string|min:40|max:200',
            'password' => 'required|string|min:8|max:255|confirmed',
            // expects password_confirmation in request
        ]);

        $tokenHash = hash('sha256', $data['token']);

        $result = DB::transaction(function () use ($tokenHash, $data, $request) {

            // Lock token row to avoid double-consume
            $onboarding = OnboardingToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$onboarding) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'message' => 'Invalid onboarding token.',
                ];
            }

            if ($onboarding->consumed_at !== null) {
                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'This onboarding token has already been used.',
                ];
            }

            if ($onboarding->expires_at->isPast()) {
                return [
                    'ok' => false,
                    'status' => 410,
                    'message' => 'This onboarding token has expired.',
                ];
            }

            $user = User::query()->lockForUpdate()->find($onboarding->user_id);

            if (!$user) {
                return [
                    'ok' => false,
                    'status' => 404,
                    'message' => 'User not found for token.',
                ];
            }

            // Set password safely
            $user->password_hash = Hash::make($data['password']);
            $user->requires_password_change = false;
            $user->password_changed_at = now();
            $user->save();

            // Consume token
            $onboarding->consumed_at = now();
            $onboarding->save();

            return [
                'ok' => true,
                'status' => 200,
                'user_global_uuid' => $user->global_user_uuid,
            ];
        });

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => [],
            ], $result['status']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password set successfully. You can now log in.',
            'data' => [
                'global_user_uuid' => $result['user_global_uuid'],
            ],
        ], 200);

    } catch (\Exception $e) {
        Log::error('Failed to consume onboarding token', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to set password.',
            'data' => [],
        ], 500);
    }
}

    /**
     * Display the specified patient.
     */
    public function show(Patient $patient): JsonResponse
    {
        try {
            // Check if user can view sensitive data
            $includeSensitive = $this->patientService->getPatientByUuid($patient->patient_uuid);
            
            if (!$includeSensitive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found.',
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => new PatientResource($patient->load(['user', 'primaryCareProvider', 'primaryCareFacility'])),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient.',
            ], 500);
        }
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $updated = $this->patientService->updatePatient($patient, $validatedData);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update patient.',
                ], 500);
            }
            
            // Refresh patient data
            $patient->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'Patient updated successfully.',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update patient', [
                'patient_uuid' => $patient->patient_uuid,
                'data' => $request->except(['medical_record_number_encrypted']),
                'error' => $e->getMessage(),
            ]);
            
            $statusCode = $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500;
            $message = $e->getMessage();
            
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $errors = $e->errors();
            } elseif (str_contains($message, 'cannot be updated')) {
                $statusCode = 400;
                $errors = ['status' => [$message]];
            } else {
                $errors = ['server' => config('app.debug') ? $message : 'Internal server error'];
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update patient.',
                'errors' => $errors,
            ], $statusCode);
        }
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        try {
            $deleted = $this->patientService->deletePatient($patient);
            
            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete patient.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Patient deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete patient', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            
            $statusCode = str_contains($e->getMessage(), 'cannot be deleted') ? 400 : 500;
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * Restore a soft-deleted patient.
     */
    public function restore($uuid): JsonResponse
    {
        try {
            $patient = $this->patientService->getPatientByUuid($uuid);
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found.',
                ], 404);
            }
            
            // Check authorization
            $this->authorize('restore', $patient);
            
            $restored = $this->patientService->restorePatient($patient);
            
            if (!$restored) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to restore patient.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Patient restored successfully.',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to restore patient', [
                'patient_uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore patient.',
            ], 500);
        }
    }

    /**
     * Update patient status.
     */
    public function updateStatus(Request $request, Patient $patient): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:active,inactive,deceased,merged,test_patient',
            ]);
            
            // Check authorization
            $this->authorize('update', $patient);
            
            if ($request->status === 'deceased') {
                $this->authorize('markDeceased', $patient);
            }
            
            $updated = $this->patientService->updatePatientStatus($patient, $request->status);
            
            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update patient status.',
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Patient status updated successfully.',
                'data' => new PatientResource($patient),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update patient status', [
                'patient_uuid' => $patient->patient_uuid,
                'status' => $request->status,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search patients.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $criteria = $request->validate([
                'status' => 'nullable|in:active,inactive,deceased,merged,test_patient',
                'biological_sex' => 'nullable|in:male,female,intersex,unknown',
                'blood_type' => 'nullable|string|max:5',
                'requires_isolation' => 'nullable|boolean',
                'date_of_birth_from' => 'nullable|date',
                'date_of_birth_to' => 'nullable|date|after_or_equal:date_of_birth_from',
                'facility_id' => 'nullable|integer|exists:facilities,id',
            ]);
            
            $patients = $this->patientService->searchPatients($criteria);
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'total' => $patients->count(),
                    'criteria' => $criteria,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search patients', [
                'criteria' => $request->all(),
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search patients.',
            ], 500);
        }
    }

    /**
     * Get patient statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $stats = $this->patientService->getPatientStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patient statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }

    /**
     * Export patient data.
     */
    public function export(Patient $patient): JsonResponse
    {
        try {
            $this->authorize('export', $patient);
            
            $data = $this->patientService->exportPatientData($patient);
            
            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'exported_at' => now()->format('Y-m-d H:i:s'),
                    'consent_level' => $patient->default_consent_level,
                    'data_sharing_allowed' => $patient->data_sharing_allowed,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to export patient data', [
                'patient_uuid' => $patient->patient_uuid,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get patients by blood type.
     */
    public function byBloodType(string $bloodType): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $patients = $this->patientService->getPatientsByBloodType($bloodType);
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'blood_type' => $bloodType,
                    'total' => $patients->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patients by blood type', [
                'blood_type' => $bloodType,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients by blood type.',
            ], 500);
        }
    }

    /**
     * Get patients requiring isolation.
     */
    public function requiringIsolation(): JsonResponse
    {
        try {
            $this->authorize('viewAny', Patient::class);
            
            $patients = $this->patientService->getPatientsRequiringIsolation();
            
            return response()->json([
                'success' => true,
                'data' => PatientResource::collection($patients),
                'meta' => [
                    'total' => $patients->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get patients requiring isolation', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patients requiring isolation.',
            ], 500);
        }
    }
}