<?php

/*
|--------------------------------------------------------------------------
| DHIS2 / eHMIS Aggregate Reporting
|--------------------------------------------------------------------------
|
| Monthly HMIS push (due every 7th): aggregate counts go to DHIS2 as a
| dataValueSet. Without instance credentials everything runs in dry-run
| mode (payload built + logged, nothing posted) so the pipeline is proven
| before MoH hands over access. Dataset/data-element UIDs come from the
| DHIS2 instance and are recorded here when provisioned.
|
*/

return [
    'enabled' => env('DHIS2_ENABLED', false),
    'dry_run' => env('DHIS2_DRY_RUN', true),
    'base_url' => env('DHIS2_BASE_URL'),
    'username' => env('DHIS2_USERNAME'),
    'password' => env('DHIS2_PASSWORD'),
    'org_unit' => env('DHIS2_ORG_UNIT'),
    'datasets' => [
        'opd_summary' => env('DHIS2_DATASET_OPD'),
    ],
];
