<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Visit Event Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for visit event recording and processing.
    |
    */

    'defaults' => [
        'payload_schema_version' => '1.0',
        'processing_latency_warning_ms' => 5000, // Warn if latency > 5 seconds
    ],

    'events' => [
        'clinical' => [
            'triage_started',
            'triage_completed',
            'vitals_recorded',
            'consultation_started',
            'consultation_completed',
            'diagnostic_ordered',
            'diagnostic_completed',
            'medication_ordered',
            'medication_administered',
            'procedure_started',
            'procedure_completed',
        ],
        
        'state_changes' => [
            'visit_created',
            'patient_arrived',
            'patient_registered',
            'visit_cancelled',
            'patient_left_ama',
            'patient_lwbs',
            'discharge_completed',
        ],
        
        'billing' => [
            'billing_updated',
            'insurance_verified',
        ],
    ],

    'validation' => [
        'max_payload_size_kb' => 1024, // 1MB max payload size
        'max_metadata_size_kb' => 256, // 256KB max metadata size
    ],

    'security' => [
        'hash_algorithm' => 'sha256',
        'require_integrity_verification' => true,
        'allow_event_updates' => false, // Events are immutable
    ],

    'performance' => [
        'pagination_default' => 15,
        'pagination_max' => 100,
        'cache_ttl_minutes' => 60,
    ],

    'logging' => [
        'enabled' => true,
        'level' => env('VISIT_EVENT_LOG_LEVEL', 'info'),
        'sensitive_fields' => [
            'event_payload.patient_ssn',
            'event_payload.credit_card',
            'metadata.ip_address',
        ],
    ],
];