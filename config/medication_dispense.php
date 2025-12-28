<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Medication Dispense Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for medication dispense module.
    |
    */

    // Default pagination
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],

    // Safety check settings
    'safety_checks' => [
        'require_override_justification' => true,
        'minimum_pharmacist_license_level' => 'active',
        'enable_allergy_check' => true,
        'enable_interaction_check' => true,
        'enable_duplicate_therapy_check' => true,
        'enable_dosage_check' => true,
        'enable_expiry_check' => true,
    ],

    // Verification settings (4-eyes principle)
    'verification' => [
        'require_different_verifier' => true,
        'maximum_verification_hours' => 24,
        'allow_self_verification' => false,
    ],

    // Pickup settings
    'pickup' => [
        'require_id_verification' => true,
        'allow_pickup_without_verification' => false,
        'maximum_pickup_days' => 30,
    ],

    // Status transitions
    'status_transitions' => [
        'allowed_from_dispensed' => ['not_picked_up', 'returned', 'destroyed'],
        'allowed_from_not_picked_up' => ['returned', 'destroyed'],
        'allowed_from_returned' => ['destroyed'],
        'allowed_from_destroyed' => [],
    ],

    // Audit logging
    'audit' => [
        'log_all_changes' => true,
        'retention_days' => 365 * 7, // 7 years for compliance
        'sensitive_fields' => [
            'patient_id',
            'prescription_details_snapshot',
            'metadata',
        ],
    ],

    // Business rules
    'business_rules' => [
        'maximum_quantity_per_dispense' => 999999.99,
        'maximum_copay_amount' => 999999.99,
        'require_patient_counseling_for' => ['controlled', 'high_risk'],
        'require_medication_guide_for' => ['branded', 'high_risk'],
    ],
];