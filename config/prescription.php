<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Prescription Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration settings for the prescription module.
    |
    */
    
    /*
    |--------------------------------------------------------------------------
    | Drug Safety Checking
    |--------------------------------------------------------------------------
    |
    | Enable/disable drug interaction and allergy checking when creating
    | prescriptions. When enabled, the system will validate against
    | external drug safety APIs (if configured).
    |
    */
    'enable_drug_safety_check' => env('ENABLE_DRUG_SAFETY_CHECK', false),
    
    /*
    |--------------------------------------------------------------------------
    | E-Prescribing Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for electronic prescribing integration.
    |
    */
    'eprescribing' => [
        'enabled' => env('EPRESCRIBING_ENABLED', true),
        'gateway' => env('EPRESCRIBING_GATEWAY', 'surescripts'),
        'test_mode' => env('EPRESCRIBING_TEST_MODE', false),
        'timeout' => env('EPRESCRIBING_TIMEOUT', 30),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    |
    | Default values for prescription fields.
    |
    */
    'defaults' => [
        'refills_allowed' => 0,
        'refills_remaining' => 0,
        'days_supply' => 30,
        'is_electronic_prescription' => true,
        'requires_prior_authorization' => false,
        'is_high_risk_medication' => false,
        'status' => 'active',
        'dispense_status' => 'pending',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Custom validation rules for prescriptions.
    |
    */
    'validation' => [
        'max_refills' => 99,
        'max_days_supply' => 365,
        'max_quantity' => 999999.99,
        'min_quantity' => 0.01,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Enable/disable detailed audit logging for prescription actions.
    |
    */
    'audit_logging' => env('PRESCRIPTION_AUDIT_LOGGING', true),
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for prescription API endpoints.
    |
    */
    'rate_limits' => [
        'create' => env('PRESCRIPTION_CREATE_RATE_LIMIT', 60), // per minute
        'update' => env('PRESCRIPTION_UPDATE_RATE_LIMIT', 120), // per minute
        'transmit' => env('PRESCRIPTION_TRANSMIT_RATE_LIMIT', 30), // per minute
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Expiration Notifications
    |--------------------------------------------------------------------------
    |
    | Configuration for prescription expiration notifications.
    |
    */
    'expiration_notifications' => [
        'enabled' => env('PRESCRIPTION_EXPIRATION_NOTIFICATIONS', true),
        'days_before' => [30, 14, 7, 1],
        'recipients' => ['patient', 'provider', 'pharmacist'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Refill Rules
    |--------------------------------------------------------------------------
    |
    | Rules governing prescription refills.
    |
    */
    'refill_rules' => [
        'allow_early_refill' => env('ALLOW_EARLY_REFILL', false),
        'early_refill_days' => env('EARLY_REFILL_DAYS', 7),
        'max_refill_attempts' => env('MAX_REFILL_ATTEMPTS', 3),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Pharmacy Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for pharmacy system integration.
    |
    */
    'pharmacy_integration' => [
        'enabled' => env('PHARMACY_INTEGRATION_ENABLED', false),
        'update_interval' => env('PHARMACY_UPDATE_INTERVAL', 5), // minutes
        'sync_dispense_status' => env('SYNC_DISPENSE_STATUS', true),
    ],
];