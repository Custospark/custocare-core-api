<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Facility Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Facility module including cache settings,
    | validation rules, and operational parameters.
    |
    */
    
    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for caching facility data. Since facilities are reference
    | data, they are heavily cached for performance.
    |
    */
    'cache' => [
        'enabled' => env('FACILITY_CACHE_ENABLED', true),
        'ttl' => env('FACILITY_CACHE_TTL', 300), // 5 minutes in seconds
        'prefix' => env('FACILITY_CACHE_PREFIX', 'facility:'),
        'tags' => env('FACILITY_CACHE_TAGS', 'facilities'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for facility validation rules and constraints.
    |
    */
    'validation' => [
        'facility_code' => [
            'pattern' => '/^[A-Z0-9_-]+$/',
            'min_length' => 3,
            'max_length' => 50,
        ],
        'phone' => [
            'pattern' => '/^[+\d\s()-]+$/',
            'min_length' => 5,
            'max_length' => 50,
        ],
        'coordinates' => [
            'latitude' => [
                'min' => -90,
                'max' => 90,
            ],
            'longitude' => [
                'min' => -180,
                'max' => 180,
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Operational Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for facility operational parameters.
    |
    */
    'operations' => [
        'default_timezone' => env('FACILITY_DEFAULT_TIMEZONE', 'UTC'),
        'supported_country_codes' => explode(',', env('FACILITY_SUPPORTED_COUNTRIES', 'USA,CAN,MEX,GBR')),
        'data_residency_regions' => explode(',', env('FACILITY_DATA_REGIONS', 'us-east,us-west,eu-west,ap-southeast')),
        'shard_strategy' => env('FACILITY_SHARD_STRATEGY', 'geographic'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Metrics Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for facility performance metrics.
    |
    */
    'metrics' => [
        'update_frequency' => env('FACILITY_METRICS_UPDATE_FREQ', 'daily'),
        'retention_days' => env('FACILITY_METRICS_RETENTION', 365),
        'thresholds' => [
            'wait_time_warning' => env('FACILITY_WAIT_TIME_WARNING', 30), // minutes
            'wait_time_critical' => env('FACILITY_WAIT_TIME_CRITICAL', 60), // minutes
            'satisfaction_warning' => env('FACILITY_SATISFACTION_WARNING', 3.0), // score out of 5
            'satisfaction_critical' => env('FACILITY_SATISFACTION_CRITICAL', 2.0), // score out of 5
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Facility API endpoints.
    |
    */
    'api' => [
        'version' => env('FACILITY_API_VERSION', 'v1'),
        'rate_limit' => env('FACILITY_API_RATE_LIMIT', 60),
        'per_page_default' => env('FACILITY_API_PER_PAGE', 15),
        'per_page_max' => env('FACILITY_API_PER_PAGE_MAX', 100),
        'include_relations' => env('FACILITY_API_INCLUDE_RELATIONS', 'parentOrganization,createdBy,updatedBy'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Audit Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for facility audit trail.
    |
    */
    'audit' => [
        'enabled' => env('FACILITY_AUDIT_ENABLED', true),
        'retention_days' => env('FACILITY_AUDIT_RETENTION', 730), // 2 years
        'log_changes' => env('FACILITY_AUDIT_LOG_CHANGES', true),
    ],
];