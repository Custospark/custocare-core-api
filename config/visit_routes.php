<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Visit Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for patient routing between departments.
    |
    */
    
    'defaults' => [
        'pagination' => [
            'per_page' => 15,
            'max_per_page' => 100,
        ],
        
        'routing' => [
            'max_wait_minutes' => 480, // 8 hours
            'max_transfer_minutes' => 120, // 2 hours
        ],
    ],
    
    'reasons' => [
        'initial_assignment' => 'Initial Assignment',
        'specialist_consultation' => 'Specialist Consultation',
        'diagnostic_imaging' => 'Diagnostic Imaging',
        'laboratory_tests' => 'Laboratory Tests',
        'surgical_procedure' => 'Surgical Procedure',
        'capacity_management' => 'Capacity Management',
        'escalation_of_care' => 'Escalation of Care',
        'de_escalation_of_care' => 'De-escalation of Care',
        'patient_request' => 'Patient Request',
        'admission_to_inpatient' => 'Admission to Inpatient',
        'discharge_processing' => 'Discharge Processing',
    ],
    
    'transport_methods' => [
        'ambulatory' => 'Ambulatory',
        'wheelchair' => 'Wheelchair',
        'stretcher' => 'Stretcher',
        'bed' => 'Bed',
        'ambulance' => 'Ambulance',
    ],
    
    'analytics' => [
        'cache_ttl' => 300, // 5 minutes
        'max_date_range_days' => 365,
    ],
];