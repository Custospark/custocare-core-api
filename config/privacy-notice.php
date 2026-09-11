<?php

/*
|--------------------------------------------------------------------------
| Custocare Privacy Notice (versioned)
|--------------------------------------------------------------------------
|
| The 9 mandatory notice items per the Data Protection and Privacy Act 2019
| (Sec 13) and the Health Data Protection Guidelines (Sec 10.2d).
| Bump `version` + `effective_at` on ANY content change: consents record the
| version they were granted under, and a version lift triggers fresh consent.
| Contacts read from env so staging/production differ without code changes.
|
*/

return [
    'version'      => env('PRIVACY_NOTICE_VERSION', '1.0.0'),
    'effective_at' => env('PRIVACY_NOTICE_EFFECTIVE_AT', '2026-09-10'),

    'controller_name'    => env('PRIVACY_CONTROLLER_NAME', 'Custocare (Custospark Company Ltd)'),
    'controller_contact' => env('PRIVACY_CONTROLLER_CONTACT', 'privacy@custospark.com'),
    'dpo_contact'        => env('PRIVACY_DPO_CONTACT', 'dpo@custospark.com'),
    'complaints_contact' => env('PRIVACY_COMPLAINTS_CONTACT', 'dpo@custospark.com'),

    'items' => [
        [
            'key'   => 'data_nature',
            'title' => 'What data we collect',
            'body'  => 'Identity details, contact details, medical history, diagnoses, prescriptions, lab results, visit records, billing records and, where you provide them, images or audio notes. We collect only what is necessary for your care.',
        ],
        [
            'key'   => 'controller',
            'title' => 'Who is responsible',
            'body'  => 'The health facility treating you is the data controller. Custocare (Custospark Company Ltd) processes your data on the facility\'s behalf as data processor.',
        ],
        [
            'key'   => 'purpose',
            'title' => 'Why we use it',
            'body'  => 'To provide treatment and continuity of care, manage appointments and visits, process billing, meet legal reporting duties (e.g. HMIS/DHIS2 aggregate reports) and, only with separate consent, research or service messages.',
        ],
        [
            'key'   => 'discretionary',
            'title' => 'Must you provide it',
            'body'  => 'Providing data for treatment is voluntary but care may be limited without it. Some disclosures (e.g. notifiable diseases, court orders) are required by law.',
        ],
        [
            'key'   => 'consequences',
            'title' => 'If you do not provide it',
            'body'  => 'We may be unable to treat you safely, reach you about results, or bill correctly. You will always be told what a refusal means before you decide.',
        ],
        [
            'key'   => 'legal_basis',
            'title' => 'Legal basis',
            'body'  => 'Your consent, and where applicable: medical purposes, legal obligations (Data Protection and Privacy Act 2019), public duty, or vital interests in emergencies.',
        ],
        [
            'key'   => 'recipients',
            'title' => 'Who sees it',
            'body'  => 'Your care team at this facility, referred providers where you are referred, Ministry of Health aggregate reporting, and processors under contract (hosting, messaging). Never sold, never public.',
        ],
        [
            'key'   => 'rights',
            'title' => 'Your rights',
            'body'  => 'Access your records free of charge (printing only), request correction, erasure or blocking where the law allows, restrict sharing, withdraw consent as easily as you gave it, and complain to our DPO and then to the Personal Data Protection Office (pdpo.go.ug).',
        ],
        [
            'key'   => 'retention',
            'title' => 'How long we keep it',
            'body'  => 'Health records are kept a minimum of 5 years from creation, longer where care continuity or the law requires. Afterwards they are archived or securely destroyed; processors delete all copies within 10 business days of contract end.',
        ],
    ],
];
