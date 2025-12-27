<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Assessment Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI Assessment module including validation rules,
    | security settings, and performance parameters.
    |
    */

    'validation' => [
        'max_input_features_size' => env('AI_ASSESSMENT_MAX_INPUT_SIZE', 10000), // characters
        'max_output_predictions' => env('AI_ASSESSMENT_MAX_PREDICTIONS', 50),
        'min_confidence_threshold' => env('AI_ASSESSMENT_MIN_CONFIDENCE', 0.1),
        'require_explanation' => env('AI_ASSESSMENT_REQUIRE_EXPLANATION', true),
    ],

    'security' => [
        'encrypt_sensitive_data' => env('AI_ASSESSMENT_ENCRYPT_DATA', true),
        'log_input_features' => env('AI_ASSESSMENT_LOG_INPUTS', false),
        'mask_patient_data' => env('AI_ASSESSMENT_MASK_PATIENT_DATA', true),
        'audit_log_retention_days' => env('AI_ASSESSMENT_AUDIT_RETENTION', 365),
    ],

    'performance' => [
        'cache_ttl' => env('AI_ASSESSMENT_CACHE_TTL', 300), // seconds
        'query_timeout' => env('AI_ASSESSMENT_QUERY_TIMEOUT', 30), // seconds
        'max_concurrent_requests' => env('AI_ASSESSMENT_MAX_CONCURRENT', 10),
        'batch_size' => env('AI_ASSESSMENT_BATCH_SIZE', 100),
    ],

    'regulatory' => [
        'require_fda_clearance' => env('AI_ASSESSMENT_REQUIRE_FDA', false),
        'require_ce_marking' => env('AI_ASSESSMENT_REQUIRE_CE', false),
        'mandatory_review_threshold' => env('AI_ASSESSMENT_REVIEW_THRESHOLD', 0.7),
        'adverse_event_reporting_days' => env('AI_ASSESSMENT_ADVERSE_EVENT_DAYS', 30),
    ],

    'notifications' => [
        'review_required' => env('AI_ASSESSMENT_NOTIFY_REVIEW', true),
        'adverse_event' => env('AI_ASSESSMENT_NOTIFY_ADVERSE', true),
        'outcome_recorded' => env('AI_ASSESSMENT_NOTIFY_OUTCOME', false),
        'email_recipients' => explode(',', env('AI_ASSESSMENT_EMAIL_RECIPIENTS', '')),
    ],

    'export' => [
        'allowed_formats' => ['csv', 'json', 'xml'],
        'max_records_per_export' => env('AI_ASSESSMENT_MAX_EXPORT', 10000),
        'include_sensitive_data' => env('AI_ASSESSMENT_EXPORT_SENSITIVE', false),
        'encryption_required' => env('AI_ASSESSMENT_EXPORT_ENCRYPT', true),
    ],
];