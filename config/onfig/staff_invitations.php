<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Staff Invitations Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for staff invitations.
    |
    */
    
    'default_expiration_days' => env('STAFF_INVITATION_EXPIRATION_DAYS', 7),
    
    'max_expiration_days' => env('STAFF_INVITATION_MAX_EXPIRATION_DAYS', 30),
    
    'resend_limit' => env('STAFF_INVITATION_RESEND_LIMIT', 3),
    
    'notification_channels' => [
        'email',
        // 'sms', // Uncomment if SMS notifications are enabled
        // 'push', // Uncomment if push notifications are enabled
    ],
    
    'allowed_statuses' => [
        'pending',
        'accepted',
        'declined',
        'expired',
    ],
    
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    
    'validation' => [
        'metadata' => [
            'max_message_length' => 500,
            'max_reason_length' => 255,
        ],
    ],
    
    'logging' => [
        'enabled' => env('STAFF_INVITATION_LOGGING_ENABLED', true),
        'level' => env('STAFF_INVITATION_LOGGING_LEVEL', 'info'),
    ],
];