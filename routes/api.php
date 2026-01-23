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

require __DIR__.'/api_v1/auth/_index.php';
require __DIR__.'/api_v1/users/_index.php';
require __DIR__.'/api_v1/patients/_index.php';
require __DIR__.'/api_v1/patientConsents/_index.php';
require __DIR__.'/api_v1/staff/_index.php';
require __DIR__.'/api_v1/staffCredential/_index.php';
require __DIR__.'/api_v1/facilities/_index.php';
require __DIR__.'/api_v1/department/_index.php';
require __DIR__.'/api_v1/facilityStaffRoles/_index.php';
require __DIR__.'/api_v1/staffInvitation/_index.php';
require __DIR__.'/api_v1/visit/_index.php';
require __DIR__.'/api_v1/visitEvent/_index.php';
require __DIR__.'/api_v1/visitActor/_index.php';
require __DIR__.'/api_v1/visitRoute/_index.php';
require __DIR__.'/api_v1/clinicalEncounter/_index.php';
require __DIR__.'/api_v1/aiAssessment/_index.php';
require __DIR__.'/api_v1/serviceCatalog/_index.php';
require __DIR__.'/api_v1/serviceVersion/_index.php';
require __DIR__.'/api_v1/billingCycle/_index.php';
require __DIR__.'/api_v1/invoiceLineItem/_index.php';
require __DIR__.'/api_v1/inventoryItem/_index.php';
require __DIR__.'/api_v1/inventoryLedger/_index.php';
require __DIR__.'/api_v1/prescription/_index.php';
require __DIR__.'/api_v1/medicationDispense/_index.php';
require __DIR__.'/api_v1/visitCurrentState/_index.php';
require __DIR__.'/api_v1/departmentQueueView/_index.php';
require __DIR__.'/api_v1/patientVisitSummaryView/_index.php';
require __DIR__.'/api_v1/auditLog/_index.php';
require __DIR__.'/api_v1/dataResidencyRule/_index.php';
require __DIR__.'/api_v1/appointment/_index.php';
require __DIR__.'/api_v1/clinicalDocument/_index.php';
require __DIR__.'/api_v1/conversation/_index.php';
require __DIR__.'/api_v1/conversationParticipant/_index.php';
require __DIR__.'/api_v1/message/_index.php';
require __DIR__.'/api_v1/messageReceipt/_index.php';
require __DIR__.'/api_v1/messageAttachment/_index.php';
require __DIR__.'/api_v1/module/_index.php';
require __DIR__.'/api_v1/facilityRoles/_index.php';
require __DIR__.'/api_v1/customerWalkIn/_index.php';

