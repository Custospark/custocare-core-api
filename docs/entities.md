## Referral - 2026-05-13 00:00:00

### Fields
- referral_uuid: string - Unique identifier for the referral
- patient_id: integer - Foreign key to the patient being referred
- facility_id: integer - Foreign key to the facility where referral originates
- referring_staff_id: integer - Foreign key to staff member making the referral
- receiving_staff_id: integer - Foreign key to staff member receiving the referral (nullable)
- referral_type: string - Type of referral (internal or external)
- referral_reason: string - Reason for the referral
- clinical_notes: text - Clinical notes associated with the referral
- external_referral_id: string - External referral ID for tracking outside referrals
- status: string - Current status (pending, accepted, rejected, completed, cancelled)
- priority: string - Priority level (low, medium, high, urgent)
- referral_date: timestamp - When the referral was created
- response_date: timestamp - When the referral was responded to
- completed_date: timestamp - When the referral was completed
- expiry_date: timestamp - When the referral expires
- metadata: array - Additional flexible data storage
- created_by_staff_id: integer - Staff who created the record
- updated_by_staff_id: integer - Staff who last updated the record

### Files Generated
- [x] Migration: `database/migrations/2026_05_13_000000_create_referrals_table.php`
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

### Provider Bindings
- `ReferralRepositoryInterface` → `ReferralRepository`
- `ReferralServiceInterface` → `ReferralService`

### API Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/referrals` | List all referrals |
| GET | `/api/v1/referrals/{id}` | Get one referral |
| POST | `/api/v1/referrals` | Create a referral |
| PUT | `/api/v1/referrals/{id}` | Update a referral |
| DELETE | `/api/v1/referrals/{id}` | Delete a referral |
| POST | `/api/v1/referrals/{id}/accept` | Accept a referral |
| POST | `/api/v1/referrals/{id}/reject` | Reject a referral |
| POST | `/api/v1/referrals/{id}/complete` | Complete a referral |
| POST | `/api/v1/referrals/{id}/cancel` | Cancel a referral |
| GET | `/api/v1/referrals/patient/{patientId}` | Get referrals for a patient |
| GET | `/api/v1/referrals/facility/{facilityId}` | Get referrals for a facility |
| GET | `/api/v1/referrals/pending` | Get pending referrals |

### Test Results
- Lint: ✅ Passed
- Migration: ✅ Ran successfully
- PHPUnit: ⚠️ Not tested (no test file created)

### SOLID Compliance Checklist
- [x] Single Responsibility
- [x] Open/Closed
- [x] Liskov Substitution
- [x] Interface Segregation
- [x] Dependency Inversion

---