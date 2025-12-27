<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API routes for the application. Routes are grouped for public access
| and authenticated access with proper middleware and permissions.
|
*/

require __DIR__.'/auth/_index.php';
require __DIR__.'/users/_index.php';
require __DIR__.'/patients/_index.php';
require __DIR__.'/patientConsents/_index.php';
require __DIR__.'/staff/_index.php';
require __DIR__.'/staffCredential/_index.php';
require __DIR__.'/facilities/_index.php';
require __DIR__.'/department/_index.php';
require __DIR__.'/facilityStaffRoles/_index.php';
require __DIR__.'/staffInvitation/_index.php';
require __DIR__.'/visit/_index.php';
require __DIR__.'/visitEvent/_index.php';

