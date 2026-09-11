<?php

/*
|--------------------------------------------------------------------------
| Custocare Data Retention Schedule
|--------------------------------------------------------------------------
|
| Minimum 5 years from creation for health data (Guidelines Sec 5.6),
| longer where care continuity or financial law requires. Review annually
| with the Data Protection Officer. Disposal only per Ministry of Public
| Service + National Records and Archives Act 2001 procedures.
|
| `years` counts from the anchor date; `method` is how disposal happens.
| Nothing here auto-deletes: retention:review lists what is due, a human
| authorizes destruction, and the certification is written to the audit log.
|
*/

return [
    'categories' => [
        'medical_records' => [
            'years' => 5, 'anchor' => 'last_encounter_at',
            'basis' => 'Guidelines Sec 5.6 minimum; longer while care continues',
            'method' => 'archive_then_cross_cut',
        ],
        'diagnostics' => [
            'years' => 5, 'anchor' => 'created_at',
            'basis' => 'Guidelines Sec 5.6 minimum',
            'method' => 'secure_delete',
        ],
        'treatment_plans' => [
            'years' => 5, 'anchor' => 'created_at',
            'basis' => 'Guidelines Sec 5.6 minimum',
            'method' => 'secure_delete',
        ],
        'billing' => [
            'years' => 7, 'anchor' => 'created_at',
            'basis' => 'Financial record practice; never below health minimum',
            'method' => 'archive_then_cross_cut',
        ],
        'consents' => [
            'years' => 5, 'anchor' => 'granted_at',
            'basis' => 'Proof burden for consent validity; follows record life',
            'method' => 'archive',
        ],
        'audit_logs' => [
            'years' => 7, 'anchor' => 'created_at',
            'basis' => 'Tamper-proof trail must outlive the records it watches',
            'method' => 'archive',
        ],
        'messages' => [
            'years' => 5, 'anchor' => 'created_at',
            'basis' => 'Clinical messages can carry PHI; health floor applies',
            'method' => 'secure_delete',
        ],
        'backups' => [
            'years' => 0, 'anchor' => 'created_at',
            'basis' => 'Rolling 30-day operational window (separate schedule)',
            'method' => 'rolling_30_day',
            'retention_days' => 30,
        ],
    ],

    // Processors must delete all copies within 10 business days of cessation
    // and certify in writing (Guidelines Sec 5.6). Certifications are written
    // to the audit log as the permanent record.
    'processor_delete_business_days' => 10,
];
