## Subscription billing v2 — 2026-05-26

### New table: `subscription_scheduled_changes`
| Column | Notes |
|--------|--------|
| `subscription_id`, `facility_id` | FKs |
| `change_type` | `upgrade`, `downgrade`, `cancel`, `plan_change` |
| `from_plan_id`, `to_plan_id` | `to_plan_id` null for cancel |
| `effective_at` | Usually `subscriptions.next_billing_date` |
| `status` | `pending`, `applied`, `cancelled` |

Unique partial index: one `pending` row per `subscription_id`.

### Subscription metadata (cancel at period end)
- `cancel_at_period_end` (bool)
- `access_ends_at` (ISO8601)
- `pending_upgrade_plan_id` (upgrade-now flow)
- `latest_quote` (cached quote + expiry)

### API endpoints (facility, under `/facilities/{facility}/subscription`)
| Method | Path | Purpose |
|--------|------|---------|
| GET | `/` | Show subscription (applies pending changes) |
| DELETE | `/` | Cancel (`mode: at_period_end` default) |
| POST | `/schedule-change` | `{ plan_id, change_type }` |
| POST | `/upgrade-now` | `{ plan_id }` → quote for proration |
| DELETE | `/scheduled-change` | Cancel pending change |
| GET | `/payment-quote` | `?intent=&plan_id=` |

### `SubscriptionResource.payment_action` (read-only)
Resolved by `SubscriptionPaymentActionResolver` on each subscription show:
| Field | Type | Notes |
|-------|------|--------|
| `required` | bool | User should complete payment |
| `pending_approval` | bool | Pending payment proof exists |
| `plan_id` | int\|null | Plan to pay for |
| `intent` | string\|null | `subscription`, `renewal`, `upgrade_now` |
| `label` | string\|null | e.g. "Complete payment" |
| `message` | string\|null | Facility-facing guidance |

### Services & bindings (`BillingServiceProvider`)
- `SubscriptionScheduledChangeRepositoryInterface` → `SubscriptionScheduledChangeRepository`
- `SubscriptionScheduledChangeServiceInterface` → `SubscriptionScheduledChangeService`
- `SubscriptionPaymentQuoteServiceInterface` → `SubscriptionPaymentQuoteService`

### Facility payment submission
- `POST /facilities/{facility}/payments` accepts proof when subscription status is `trial`, `active`, `past_due`, or `suspended` (`Subscription::canAcceptFacilityPayment()`), not only when `has_access` is true.
- Duplicate pending proof still returns 422 from `PaymentService`.

### Payment types
- Added `upgrade_proration` — on admin approve calls `SubscriptionService::upgradeNow()`.

---

## Prescriptions — 2026-05-21

### API Endpoints
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/prescriptions/visit/{visitId}` | Get all prescriptions for a specific visit |

**Note:** The visit-scoped endpoint returns `PrescriptionResource[]` wrapped in the standard `{ success, message, data }` response format. Each resource now includes `visit_id` and `patient_id` fields.

### Files
- `app/Http/Controllers/Api/PrescriptionController.php` — `visitPrescriptions()` method added
- `app/Http/Resources/PrescriptionResource.php` — returns `visit_id`, `patient_id`

### Provider Bindings
No new bindings — uses existing `PrescriptionRepositoryInterface` → `PrescriptionRepository` with `getByVisit()` method.

---

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

## Ambulance Services — 2026-05-14

### Entities

#### 1. Ambulance (Vehicle)
- ambulance_uuid: string - UUID identifier
- facility_id: FK → facilities (home base)
- crew_team_lead_staff_id: FK → staff (nullable)
- vehicle_identifier: string - License plate / fleet number (unique)
- vehicle_type: string - bls, als, critical_care, patient_transport, type_i, type_ii, type_iii
- equipment_level: string (nullable)
- status: string - available, in_service, out_of_service, maintenance, decommissioned
- last_service_date: date
- next_service_due_date: date
- current_mileage: integer
- capacity: integer
- features: json - Onboard equipment list
- metadata: json

#### 2. Ambulance Trip
- trip_uuid: string - UUID identifier
- facility_id: FK → facilities (dispatching)
- patient_id: FK → patients
- visit_id: FK → visits (nullable)
- ambulance_id: FK → ambulances (nullable)
- dispatch_staff_id: FK → staff
- requesting_staff_id: FK → staff (nullable)
- trip_type: string - emergency, non_emergency, inter_facility_transfer, standby, special_event
- priority: string - low, medium, high, urgent
- status: string - requested → dispatched → en_route → on_scene → transporting → at_destination → completed/cancelled
- pickup_location / pickup_facility_id
- destination_location / destination_facility_id
- mileage: decimal
- estimated_duration_minutes: integer
- Timeline: dispatched_at, en_route_at, on_scene_at, patient_contact_at, depart_scene_at, at_destination_at, completed_at, cancelled_at

#### 3. Ambulance Trip Log
- trip_id: FK → ambulance_trips
- event_type: string - status_change, location_update, patient_condition, note, handoff, delay
- description: text
- recorded_at: timestamp
- recorded_by_staff_id: FK → staff

#### 4. Ambulance Crew Member
- ambulance_id: FK → ambulances (nullable)
- staff_id: FK → staff
- role: string - driver, attendant, paramedic, emt, nurse, doctor, crew_lead
- is_primary_driver: boolean
- certification_expiry: date (nullable)
- active: boolean
- assigned_at / unassigned_at: timestamps

### Files Generated/Updated
- [x] 4 Migrations (ambulances, trips, trip_logs, crew_members)
- [x] 4 Models (Ambulance, AmbulanceTrip, AmbulanceTripLog, AmbulanceCrewMember)
- [x] 4 Repository Interfaces
- [x] 4 Repository Implementations
- [x] 4 Service Interfaces
- [x] 4 Service Implementations
- [x] 7 Request files (Store + Update per entity, plus Store for TripLog)
- [x] 8 Resource/Collection files
- [x] 4 Controllers (Ambulance, Trip, TripLog, CrewMember)
- [x] 3 Route files + api.php registration
- [x] Service Provider: App\Providers\AmbulanceServiceProvider
- [x] 4 Factories

### Provider Bindings
- AmbulanceRepositoryInterface → AmbulanceRepository
- AmbulanceServiceInterface → AmbulanceService
- AmbulanceTripRepositoryInterface → AmbulanceTripRepository
- AmbulanceTripServiceInterface → AmbulanceTripService
- AmbulanceTripLogRepositoryInterface → AmbulanceTripLogRepository
- AmbulanceTripLogServiceInterface → AmbulanceTripLogService
- AmbulanceCrewMemberRepositoryInterface → AmbulanceCrewMemberRepository
- AmbulanceCrewMemberServiceInterface → AmbulanceCrewMemberService

### Test Results
- Lint: ✅ Passed (all 37 files)
- Migration: ✅ All 4 migrations ran successfully
- Routes: ✅ All 33 routes registered

---

## EnsureStaffFacilityAccess Middleware — 2026-05-18

### Purpose
Conditional gate that validates staff-facility assignments on every request. Passes through when headers are absent.

### Behavior
| Headers Present | Action |
|----------------|--------|
| None | Pass through |
| `X-Staff-Id` only | Resolve staff record (404 if invalid), pass through |
| `X-Staff-Id` + `X-Active-Facility-Id` (or `X-Facility-Id`) | Resolve staff (404), resolve facility (404), verify `facility_staff_roles` assignment with `assignment_status` in `['active', 'on_leave']` and dates in range (403 if none) |

### HTTP Responses
| Status | Condition |
|--------|-----------|
| 404 | Staff ID or Facility ID doesn't match any record |
| 403 | Staff has no active/on-leave assignment at the facility |
| 200 | Validation passes (or headers absent) |

### Files
- [x] Middleware: `app/Http/Middleware/EnsureStaffFacilityAccess.php`
- [x] Registration: `bootstrap/app.php` — global middleware (appended to every request)
- [x] Test: `tests/Feature/EnsureStaffFacilityAccessMiddlewareTest.php` (20 tests)

### Test Results
- PHP Syntax: ✅ Passed (all files)
- PHPUnit: ✅ 20/20 tests passing

### Security Test Coverage
| Test | Scenario |
|------|----------|
| `test_rejects_non_numeric_staff_id` | UUID string sent as staff ID → 404 |
| `test_rejects_non_numeric_facility_id` | UUID string sent as facility ID → 404 |
| `test_sql_injection_in_staff_id_safely_returns_404` | SQL-injection-like staff ID safely cast to int → 404 |
| `test_sql_injection_in_facility_id_safely_returns_404` | SQL-injection-like facility ID safely cast to int → 404 |
| `test_rejects_expired_assignment_date` | Assignment's effective_to is in the past → 403 |
| `test_rejects_future_effective_from` | Assignment hasn't started yet → 403 |
| `test_passes_through_empty_header_values` | Empty header values treated as absent → 200 |
| `test_rejects_negative_staff_id` | Negative staff ID → 404 |
| `test_rejects_huge_integer_staff_id` | Overflow-sized staff ID → 404 |
| `test_rejects_known_staff_at_wrong_facility_using_facility_uuid` | UUID instead of numeric facility ID → 404 |

---

## Laravel Reverb Real-Time Events — 2026-05-18

### Purpose
Eliminate frontend polling by broadcasting real-time events over WebSockets when data changes.

### Architecture
```
User Action → Controller/Service → Dispatch Event → Reverb → Echo Client → Invalidate React Query → Re-render
```

### Events

| Event | Channel | Broadcast As | Fired From | Frontend Effect |
|-------|---------|-------------|------------|-----------------|
| `MessageStatsUpdated` | `user.{userId}` | `MessageStatsUpdated` | `MessageController@store`, `@destroy`, `@restore`, `@markRead`, `@markUnread`, `@send`, `@permanentDelete` | Invalidates `message-stats` query — replaces 20s polling |
| `StaffPresenceChanged` | `user.{userId}` | `StaffPresenceChanged` | `StaffPresenceService@setPresence` | Invalidates `staff-presence` query |
| `SpaceOccupancyChanged` | `facility.{facilityId}` | `SpaceOccupancyChanged` | `StaffSpaceAssignmentController@assignMySpace`, `@assignSpaceByAdmin`, `@releaseMySpace`, `@releaseSpaceByAdmin` | Invalidates `staff-space-assignment` occupancy query |

### Channel Authorization (`routes/channels.php`)
- `user.{id}` — Authenticated user ID must match the channel ID
- `facility.{id}` — User must have an active or on-leave `facility_staff_roles` assignment at that facility

### Files Created
- [x] Event: `app/Events/MessageStatsUpdated.php` — `ShouldBroadcast`, private channel
- [x] Event: `app/Events/StaffPresenceChanged.php` — `ShouldBroadcast`, private channel  
- [x] Event: `app/Events/SpaceOccupancyChanged.php` — `ShouldBroadcast`, private channel

### Files Modified
- [x] `routes/channels.php` — Added `user.{id}` and `facility.{id}` channel auth
- [x] `app/Http/Controllers/Api/MessageController.php` — Fires `MessageStatsUpdated` after mutations
- [x] `app/Services/StaffPresence/StaffPresenceService.php` — Fires `StaffPresenceChanged` after presence change
- [x] `app/Http/Controllers/Api/StaffSpaceAssignmentController.php` — Fires `SpaceOccupancyChanged` after assign/release
- [x] `config/reverb.php` — Published (pre-existing, now active)

### Frontend Changes
- [x] Installed `laravel-echo` + `pusher-js`
- [x] Created `echo.ts` — Echo instance configured for Reverb
- [x] Created `useReverbListener.ts` — Subscribes to `user.{id}` + `facility.{id}` channels, invalidates React Query on events
- [x] Mounted in `App.tsx` — Runs once app-wide
- [x] Removed `refetchInterval: 20_000` from `useGetMessageStats` — Now event-driven
- [x] Added `VITE_REVERB_*` env vars to `.env.development`

### Requirements
- Laravel Reverb server must be running: `php artisan reverb:start`
- Frontend env vars must match backend Reverb credentials
- Auth token must be present in localStorage for Echo private channel auth

---

## Message Center — `messages` body encryption at rest — 2026-05-26

### Purpose
Store message **body** ciphertext in the database; API consumers still send/receive plaintext `body` over HTTPS. **Subject** stays plaintext for folder search and alphabetical sort.

### Storage

| Column | Role |
|--------|------|
| `body_encrypted` | Laravel `encrypt()` ciphertext (AES-256-CBC, `APP_KEY`) |
| `body` | Legacy plaintext — cleared on write; used only as decrypt fallback for unmigrated rows |

### API (unchanged contract)

| Method | Path | Body field |
|--------|------|------------|
| POST | `/api/messages` | Request/response `body` is plaintext JSON |
| PUT | `/api/messages/{id}` | Same |
| GET | `/api/messages`, `/api/messages/{id}` | Decrypted `body` in JSON |

### Search behaviour
- Folder search matches **subject**, sender name, recipient email/phone/name, labels.
- **Body text is not searchable** while encrypted (v1 trade-off).

### Implementation
- `App\Services\Message\MessageBodyCipher` — `encrypt()` / `decrypt()` helpers
- `App\Models\Message` — `body` accessor/mutator; `body_encrypted` hidden from serialization
- Migration `2026_05_26_120000_add_body_encrypted_to_messages_table` — adds column + backfills existing rows

### Operations
```bash
php artisan migrate
```

---