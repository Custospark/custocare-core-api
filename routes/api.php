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

