<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vital;
use App\Repositories\Contracts\VitalRepositoryInterface;
use App\Services\Contracts\VitalServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VitalService implements VitalServiceInterface
{
    /**
     * @var VitalRepositoryInterface
     */
    protected VitalRepositoryInterface $vitalRepository;

    /**
     * Constructor.
     *
     * @param VitalRepositoryInterface $vitalRepository
     */
    public function __construct(VitalRepositoryInterface $vitalRepository)
    {
        $this->vitalRepository = $vitalRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllVitals(array $filters = [], int $perPage = 20): array
    {
        try {
            $vitals = $this->vitalRepository->getAllPaginated($filters, $perPage);

            return [
                'success' => true,
                'data' => $vitals,
                'message' => 'Vital records retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get vital records', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve vital records',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVitalById(int $id): array
    {
        try {
            $vital = $this->vitalRepository->findByIdWithRelations($id, ['facility', 'patient', 'staff', 'visit']);

            if (!$vital) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Vital record not found',
                    'error' => 'Vital record not found',
                ];
            }

            return [
                'success' => true,
                'data' => $vital,
                'message' => 'Vital record retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get vital record by ID', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve vital record',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getPatientVitals(int $patientId, array $filters = [], int $perPage = 20): array
    {
        try {
            $vitals = $this->vitalRepository->getPaginatedByPatient($patientId, $filters, $perPage);

            return [
                'success' => true,
                'data' => $vitals,
                'message' => 'Patient vital records retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get patient vital records', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve patient vital records',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getLatestPatientVitals(int $patientId): array
    {
        try {
            $vital = $this->vitalRepository->getLatestByPatient($patientId);

            return [
                'success' => true,
                'data' => $vital,
                'message' => $vital ? 'Latest vital record retrieved successfully' : 'No vital records found for patient',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get latest patient vital record', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve latest vital record',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisitVitals(int $visitId): array
    {
        try {
            $vitals = $this->vitalRepository->getByVisit($visitId);

            return [
                'success' => true,
                'data' => $vitals,
                'message' => 'Visit vital records retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get visit vital records', [
                'error' => $e->getMessage(),
                'visit_id' => $visitId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve visit vital records',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createVital(array $data, int $recordedByStaffId): array
    {
        DB::beginTransaction();

        try {
            // Validate the data
            $validatedData = $this->validateVitalData($data);

            // Add staff_id
            $validatedData['staff_id'] = $recordedByStaffId;

            // Set measured_at if not provided
            if (!isset($validatedData['measured_at'])) {
                $validatedData['measured_at'] = now();
            }

            // Calculate BMI if height and weight are provided
            $validatedData = $this->calculateAndSetBmi($validatedData);

            // Generate clinical alert
            $clinicalAlert = $this->generateClinicalAlertFromData($validatedData);
            if ($clinicalAlert) {
                $validatedData['clinical_alert'] = $clinicalAlert;
            }

            $vital = $this->vitalRepository->create($validatedData);

            // Update flag status after creation
            $vital->updateFlagStatus();
            if ($vital->flag_status !== ($validatedData['flag_status'] ?? null)) {
                $vital->save();
            }

            DB::commit();

            Log::info('Vital record created successfully', [
                'vital_id' => $vital->id,
                'patient_id' => $vital->patient_id,
                'staff_id' => $recordedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $vital->fresh(),
                'message' => 'Vital record created successfully',
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
            Log::error('Failed to create vital record', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to create vital record',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateVital(int $id, array $data, int $updatedByStaffId): array
    {
        DB::beginTransaction();

        try {
            $vital = $this->vitalRepository->findById($id);

            if (!$vital) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Vital record not found',
                    'error' => 'Vital record not found',
                ];
            }

            $validatedData = $this->validateVitalData($data, $vital);

            // Calculate BMI if height or weight changed
            if (isset($validatedData['height']) || isset($validatedData['weight']) ||
                isset($validatedData['height_unit']) || isset($validatedData['weight_unit'])) {
                $updatedData = array_merge($vital->toArray(), $validatedData);
                $updatedData = $this->calculateAndSetBmi($updatedData);
                if (isset($updatedData['bmi'])) {
                    $validatedData['bmi'] = $updatedData['bmi'];
                }
            }

            // Generate clinical alert
            $updatedData = array_merge($vital->toArray(), $validatedData);
            $clinicalAlert = $this->generateClinicalAlertFromData($updatedData);
            if ($clinicalAlert) {
                $validatedData['clinical_alert'] = $clinicalAlert;
            } elseif (isset($validatedData['clinical_alert']) && !$clinicalAlert) {
                $validatedData['clinical_alert'] = null;
            }

            $updated = $this->vitalRepository->update($vital, $validatedData);

            if (!$updated) {
                throw new \Exception('Failed to update vital record');
            }

            // Update flag status
            $vital->fresh()->updateFlagStatus();
            $vital->save();

            DB::commit();

            Log::info('Vital record updated successfully', [
                'vital_id' => $vital->id,
                'updated_by' => $updatedByStaffId,
            ]);

            return [
                'success' => true,
                'data' => $vital->fresh(),
                'message' => 'Vital record updated successfully',
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
            Log::error('Failed to update vital record', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to update vital record',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteVital(int $id): array
    {
        DB::beginTransaction();

        try {
            $vital = $this->vitalRepository->findById($id);

            if (!$vital) {
                return [
                    'success' => false,
                    'message' => 'Vital record not found',
                    'error' => 'Vital record not found',
                ];
            }

            $deleted = $this->vitalRepository->delete($vital);

            if (!$deleted) {
                throw new \Exception('Failed to delete vital record');
            }

            DB::commit();

            Log::info('Vital record deleted successfully', [
                'vital_id' => $id,
            ]);

            return [
                'success' => true,
                'message' => 'Vital record deleted successfully',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete vital record', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete vital record',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAbnormalVitals(?int $facilityId = null, int $limit = 50): array
    {
        try {
            $vitals = $this->vitalRepository->getAbnormalVitals($facilityId, $limit);

            return [
                'success' => true,
                'data' => $vitals,
                'message' => 'Abnormal vital records retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get abnormal vital records', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve abnormal vital records',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCriticalVitals(?int $facilityId = null, int $limit = 50): array
    {
        try {
            $vitals = $this->vitalRepository->getCriticalVitals($facilityId, $limit);

            return [
                'success' => true,
                'data' => $vitals,
                'message' => 'Critical vital records retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get critical vital records', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve critical vital records',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVitalTrend(int $patientId, string $vitalType, int $limit = 10): array
    {
        try {
            $trend = $this->vitalRepository->getVitalTrend($patientId, $vitalType, $limit);

            return [
                'success' => true,
                'data' => $trend,
                'message' => 'Vital trend retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get vital trend', [
                'error' => $e->getMessage(),
                'patient_id' => $patientId,
                'vital_type' => $vitalType,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve vital trend',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVitalStatistics(int $facilityId, string $startDate, string $endDate): array
    {
        try {
            $stats = $this->vitalRepository->getVitalStatistics($facilityId, $startDate, $endDate);

            return [
                'success' => true,
                'data' => $stats,
                'message' => 'Vital statistics retrieved successfully',
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get vital statistics', [
                'error' => $e->getMessage(),
                'facility_id' => $facilityId,
            ]);

            return [
                'success' => false,
                'data' => [],
                'message' => 'Failed to retrieve vital statistics',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function calculateAndSetBmi(array $data): array
    {
        $height = $data['height'] ?? null;
        $weight = $data['weight'] ?? null;
        $heightUnit = $data['height_unit'] ?? 'cm';
        $weightUnit = $data['weight_unit'] ?? 'kg';

        if (!$height || !$weight) {
            return $data;
        }

        // Convert to meters and kg
        $heightInMeters = $heightUnit === 'cm' ? $height / 100 : $height * 0.0254;
        $weightInKg = $weightUnit === 'kg' ? $weight : $weight * 0.453592;

        if ($heightInMeters > 0) {
            $bmi = round($weightInKg / ($heightInMeters * $heightInMeters), 2);
            $data['bmi'] = $bmi;
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function generateClinicalAlertFromData(array $data): ?string
    {
        $alerts = [];

        // Temperature check
        if (isset($data['temperature'])) {
            $tempInCelsius = ($data['temperature_unit'] ?? 'celsius') === 'celsius'
                ? $data['temperature']
                : ($data['temperature'] - 32) * 5 / 9;

            if ($tempInCelsius >= 38.0) {
                $alerts[] = 'Fever detected';
            }
            if ($tempInCelsius < 35.0) {
                $alerts[] = 'Hypothermia detected';
            }
        }

        // Oxygen saturation check
        if (isset($data['oxygen_saturation']) && $data['oxygen_saturation'] < 90) {
            $alerts[] = 'Low oxygen saturation';
        }

        // Heart rate check
        if (isset($data['heart_rate'])) {
            if ($data['heart_rate'] > 120) {
                $alerts[] = 'Severe tachycardia';
            } elseif ($data['heart_rate'] > 100) {
                $alerts[] = 'Tachycardia';
            } elseif ($data['heart_rate'] < 50) {
                $alerts[] = 'Severe bradycardia';
            } elseif ($data['heart_rate'] < 60) {
                $alerts[] = 'Bradycardia';
            }
        }

        // Respiratory rate check
        if (isset($data['respiratory_rate'])) {
            if ($data['respiratory_rate'] > 30) {
                $alerts[] = 'Severe tachypnea';
            } elseif ($data['respiratory_rate'] > 20) {
                $alerts[] = 'Tachypnea';
            } elseif ($data['respiratory_rate'] < 10) {
                $alerts[] = 'Bradypnea';
            }
        }

        // Blood pressure check
        if (isset($data['systolic_bp']) && isset($data['diastolic_bp'])) {
            if ($data['systolic_bp'] >= 180 || $data['diastolic_bp'] >= 120) {
                $alerts[] = 'Hypertensive crisis';
            } elseif ($data['systolic_bp'] >= 140 || $data['diastolic_bp'] >= 90) {
                $alerts[] = 'Hypertension';
            } elseif ($data['systolic_bp'] < 90) {
                $alerts[] = 'Hypotension';
            }
        }

        // Pain score check
        if (isset($data['pain_score']) && $data['pain_score'] >= 7) {
            $alerts[] = 'Severe pain reported';
        }

        return empty($alerts) ? null : implode('; ', $alerts);
    }

    /**
     * Validate vital data.
     *
     * @param array $data
     * @param Vital|null $vital
     * @return array
     * @throws ValidationException
     */
    private function validateVitalData(array $data, ?Vital $vital = null): array
    {
        $rules = [
            'facility_id' => 'required|exists:facilities,id',
            'visit_id' => 'required|exists:visits,id',
            'patient_id' => 'required|exists:patients,id',
            'temperature' => 'nullable|numeric|min:25|max:45',
            'temperature_unit' => 'nullable|in:celsius,fahrenheit',
            'heart_rate' => 'nullable|numeric|min:0|max:300',
            'respiratory_rate' => 'nullable|numeric|min:0|max:100',
            'systolic_bp' => 'nullable|numeric|min:30|max:300',
            'diastolic_bp' => 'nullable|numeric|min:30|max:200',
            'bp_position' => 'nullable|in:sitting,standing,supine,lying',
            'bp_location' => 'nullable|string|max:50',
            'oxygen_saturation' => 'nullable|numeric|min:0|max:100',
            'oxygen_flow_rate' => 'nullable|integer|min:0|max:50',
            'oxygen_delivery_device' => 'nullable|string|max:100',
            'height' => 'nullable|numeric|min:10|max:300',
            'height_unit' => 'nullable|in:cm,inches',
            'weight' => 'nullable|numeric|min:0.1|max:500',
            'weight_unit' => 'nullable|in:kg,lbs',
            'pain_score' => 'nullable|numeric|min:0|max:10',
            'pain_scale_type' => 'nullable|in:numeric,faces,visual_analog',
            'pain_location' => 'nullable|string|max:200',
            'head_circumference' => 'nullable|numeric|min:20|max:100',
            'length' => 'nullable|numeric|min:20|max:150',
            'measured_at' => 'nullable|date',
            'measurement_method' => 'nullable|string|max:100',
            'device_id' => 'nullable|string|max:100',
            'consciousness_level' => 'nullable|in:alert,verbal,pain,unresponsive',
            'general_appearance' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'percentiles' => 'nullable|array',
            'clinical_alert' => 'nullable|string',
        ];

        // For updates, make fields sometimes required
        if ($vital) {
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