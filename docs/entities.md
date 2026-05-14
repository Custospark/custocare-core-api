## Referral - 2026-05-13 00:00:00

### Fields
- referral_uuid: string - Unique identifier for the referral
- patient_id: integer - Foreign key to the patient being referred
- facility_id: integer - Foreign key to the **referring** (source) facility
- receiving_facility_id: integer - Foreign key to the **receiving** (destination) facility (nullable)
- referring_staff_id: integer - Foreign key to staff making the referral (nullable — facility→facility referrals)
- receiving_staff_id: integer - Foreign key to staff receiving the referral (nullable)
- referral_type: string - Type: internal (same facility) or external (cross-facility)
- referral_reason: string - Reason for the referral
- clinical_notes: text - Clinical notes associated with the referral
- external_referral_id: string - External tracking ID (nullable)
- status: string - pending, accepted, rejected, completed, cancelled
- priority: string - low, medium, high, urgent
- referral_date: timestamp - When the referral was created
- response_date: timestamp - When the referral was responded to
- completed_date: timestamp - When the referral was completed
- expiry_date: timestamp - When the referral expires
- metadata: array - Additional flexible data storage
- created_by_staff_id: integer - Staff who created the record
- updated_by_staff_id: integer - Staff who last updated the record

### Files Generated/Updated
- [x] Migration: `database/migrations/2026_05_13_000000_create_referrals_table.php`
- [x] Migration: `database/migrations/2026_05_14_000001_add_receiving_facility_to_referrals_table.php`
- [x] Model: `app/Models/Referral.php`
- [x] Repository Interface: `app/Repositories/Interfaces/ReferralRepositoryInterface.php`
- [x] Repository: `app/Repositories/ReferralRepository.php`
- [x] Service Interface: `app/Services/Interfaces/ReferralServiceInterface.php`
- [x] Service: `app/Services/ReferralService.php`
- [x] Request: `app/Http/Requests/Referral/StoreReferralRequest.php`
- [x] Request: `app/Http/Requests/Referral/UpdateReferralRequest.php`
- [x] Resource: `app/Http/Resources/ReferralResource.php`
- [x] Collection: `app/Http/Resources/ReferralCollection.php`
- [x] Controller: `app/Http/Controllers/Api/ReferralController.php`
- [x] API Routes: `routes/api_v1/referrals/_index.php`
- [x] Registered in: `routes/api.php`
- [x] Provider: `app/Providers/ReferralServiceProvider.php` + registered in `config/app.php`
- [x] Factory: `database/factories/ReferralFactory.php`

### Provider Bindings
- `ReferralRepositoryInterface` → `ReferralRepository`
- `ReferralServiceInterface` → `ReferralService`

### Supported Referral Scenarios

| # | Scenario | Source Facility | Dest Facility | Referring Staff | Receiving Staff |
|---|----------|----------------|---------------|-----------------|-----------------|
| 1 | Same-facility staff→staff | Facility A | Facility A (or null) | Dr. Smith | Dr. Jones |
| 2 | Cross-facility facility→facility | Facility A | Facility B | null | null |
| 3 | Cross-facility staff→staff | Facility A | Facility B | Dr. Smith | Dr. Jones |
| 4 | Cross-facility facility→staff | Facility A | Facility B | null | Dr. Jones |

### Model Relationships
- `referringFacility()` / `facility()` → Source facility
- `receivingFacility()` → Destination facility
- `referringStaff()` → Staff who made the referral
- `receivingStaff()` → Staff who received/accepts the referral
- `patient()` → The referred patient
- `createdBy()` / `updatedBy()` → Staff audit trail

### API Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/referrals` | List all referrals (with filters) |
| GET | `/api/v1/referrals/{uuid}` | Get one referral by UUID |
| POST | `/api/v1/referrals` | Create a referral |
| PUT | `/api/v1/referrals/{uuid}` | Update a referral |
| DELETE | `/api/v1/referrals/{uuid}` | Delete a referral |
| POST | `/api/v1/referrals/{uuid}/accept` | Accept a referral |
| POST | `/api/v1/referrals/{uuid}/reject` | Reject a referral |
| POST | `/api/v1/referrals/{uuid}/complete` | Complete a referral |
| POST | `/api/v1/referrals/{uuid}/cancel` | Cancel a referral |
| GET | `/api/v1/referrals/patient/{patientId}` | Get referrals for a patient |
| GET | `/api/v1/referrals/facility/{facilityId}` | Get referrals involving a facility |
| GET | `/api/v1/referrals/from-facility/{facilityId}` | Get referrals **from** a facility |
| GET | `/api/v1/referrals/to-facility/{facilityId}` | Get referrals **to** a facility |
| GET | `/api/v1/referrals/pending` | Get pending referrals |

### Available Query Filters
- `status`, `priority`, `referral_type`
- `referring_staff_id`, `receiving_staff_id`, `receiving_facility_id`
- `from_date`, `to_date` (filter by `referral_date`)
- `search` (searches reason, clinical notes, patient name, facility name)
- `per_page` (pagination, default 15)

### Test Results
- Lint: ✅ Passed (all 12 files)
- Migration: ✅ Ran successfully (`2026_05_14_000001_add_receiving_facility_to_referrals_table`)
- PHPUnit: ⚠️ Not tested (no test file created)

### SOLID Compliance Checklist
- [x] Single Responsibility — one class per concern
- [x] Open/Closed — interfaces allow extension without modification
- [x] Liskov Substitution — repositories are substitutable
- [x] Interface Segregation — separate Repository and Service interfaces
- [x] Dependency Inversion — services depend on interfaces, not concretions

---