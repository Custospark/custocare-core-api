# Ambulance Services — Architecture Plan
**Date:** 2026-05-14

---

## 1. Overview

Introduce a complete ambulance service delivery module covering vehicle management, crew assignment, dispatch/routing, trip tracking, and integration with existing Patient, Facility, Staff, and Visit entities.

---

## 2. Entities

### 2.1 Ambulance (Vehicle)
```php
ambulances
├── id                          PK
├── ambulance_uuid              UUID (unique index)
├── facility_id                 FK → facilities (home base)
├── crew_team_lead_staff_id     FK → staff (nullable, crew lead)
├── vehicle_identifier          string — license plate or fleet #
├── vehicle_type                enum: basic_life_support, advanced_life_support, critical_care, patient_transport, specialty
├── equipment_level             enum: type_i, type_ii, type_iii, type_ii_sct, medium_duty, other
├── status                      enum: available, in_service, out_of_service, maintenance, decommissioned
├── last_service_date           date
├── next_service_due_date       date
├── current_mileage             integer
├── capacity                    integer — max patient capacity
├── features                    json — onboard equipment list
├── metadata                    json
├── created_by_staff_id         FK → staff
├── updated_by_staff_id         FK → staff
├── created_at, updated_at, deleted_at (soft deletes)
```

### 2.2 Ambulance Crew Member
```php
ambulance_crew_members
├── id                          PK
├── ambulance_id                FK → ambulances (nullable — unassigned staff ok)
├── staff_id                    FK → staff
├── role                        enum: driver, attendant, paramedic, emt, nurse, doctor, crew_lead
├── is_primary_driver           bool
├── certification_expiry        date
├── active                      bool (currently assigned to this vehicle)
├── assigned_at                 timestamp
├── unassigned_at               timestamp (nullable)
├── metadata                    json
├── created_at, updated_at
```

### 2.3 Ambulance Dispatch / Trip
```php
ambulance_trips
├── id                          PK
├── trip_uuid                   UUID (unique index)
├── facility_id                 FK → facilities (requesting/dispatching facility)
├── patient_id                  FK → patients
├── visit_id                    FK → visits (nullable — link to ongoing visit)
├── ambulance_id                FK → ambulances (nullable until assigned)
├── dispatch_staff_id           FK → staff (who dispatched)
├── requesting_staff_id         FK → staff (who requested if different)
├── trip_type                   enum: emergency, non_emergency, inter_facility_transfer, standby, special_event
├── priority                    enum: low, medium, high, urgent
├── status                      enum: requested, dispatched, en_route, on_scene, transporting, at_destination, completed, cancelled
├── pickup_location             text (address or facility name)
├── pickup_facility_id          FK → facilities (nullable — if pickup is a facility)
├── destination_location        text (address or facility name)
├── destination_facility_id     FK → facilities (nullable — if destination is a facility)
├── dispatch_notes              text
├── trip_notes                  text
├── mileage                     decimal (total trip miles)
├── estimated_duration_minutes  integer
├── actual_duration_minutes     integer (calculated)
│
├── dispatched_at               timestamp
├── en_route_at                 timestamp
├── on_scene_at                 timestamp
├── patient_contact_at          timestamp
├── depart_scene_at             timestamp
├── at_destination_at           timestamp
├── completed_at                timestamp
├── cancelled_at                timestamp
├── cancellation_reason         text
│
├── billing_code                string — for service billing
├── charge_amount               decimal
├── metadata                    json
├── created_by_staff_id         FK → staff
├── updated_by_staff_id         FK → staff
├── created_at, updated_at, deleted_at (soft deletes)
```

### 2.4 Ambulance Trip Log (per-crew entry / events)
```php
ambulance_trip_logs
├── id                          PK
├── trip_id                     FK → ambulance_trips
├── event_type                  enum: status_change, location_update, patient_condition, note, handoff, delay
├── description                 text
├── recorded_at                 timestamp (default now)
├── recorded_by_staff_id        FK → staff
├── metadata                    json
├── created_at, updated_at
```

---

## 3. Data Flow & Lifecycle

### Ambulance Lifecycle
```
available → in_service → out_of_service → maintenance → available
                                                  ↓
                                          decommissioned
```

### Trip Lifecycle
```
requested → dispatched → en_route → on_scene → transporting → at_destination → completed
    ↓          ↓           ↓           ↓           ↓                ↓
 cancelled  cancelled   cancelled   cancelled   cancelled       cancelled
```

### Trip Timeline Events
| Event | Column | Description |
|-------|--------|-------------|
| Dispatch | `dispatched_at` | Ambulance assigned and crew notified |
| En Route | `en_route_at` | Ambulance departs to pickup location |
| On Scene | `on_scene_at` | Arrived at pickup location |
| Patient Contact | `patient_contact_at` | Patient loaded |
| Depart Scene | `depart_scene_at` | Departed with patient |
| At Destination | `at_destination_at` | Arrived at drop-off |
| Complete | `completed_at` | Handoff done, return to service |

---

## 4. Relationships

```
Ambulance ──belongsTo──→ Facility (home base)
Ambulance ──hasMany──→ AmbulanceCrewMember
Ambulance ──hasMany──→ AmbulanceTrip

AmbulanceTrip ──belongsTo──→ Facility (dispatching)
AmbulanceTrip ──belongsTo──→ Patient
AmbulanceTrip ──belongsTo──→ Visit (optional)
AmbulanceTrip ──belongsTo──→ Ambulance
AmbulanceTrip ──hasMany──→ AmbulanceTripLog

AmbulanceCrewMember ──belongsTo──→ Ambulance
AmbulanceCrewMember ──belongsTo──→ Staff
```

---

## 5. API Endpoints

### Ambulances (Vehicle Management)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/ambulances` | List all ambulances (paginated, filterable) |
| GET | `/api/v1/ambulances/{uuid}` | Get one ambulance |
| POST | `/api/v1/ambulances` | Create ambulance |
| PUT | `/api/v1/ambulances/{uuid}` | Update ambulance |
| DELETE | `/api/v1/ambulances/{uuid}` | Soft-delete ambulance |
| GET | `/api/v1/ambulances/facility/{facilityId}` | Ambulances by home facility |
| GET | `/api/v1/ambulances/available` | Currently available ambulances |

### Ambulance Trips (Dispatch & Tracking)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/ambulance-trips` | List all trips (filterable) |
| GET | `/api/v1/ambulance-trips/{uuid}` | Get one trip |
| POST | `/api/v1/ambulance-trips` | Create/request trip |
| PUT | `/api/v1/ambulance-trips/{uuid}` | Update trip |
| DELETE | `/api/v1/ambulance-trips/{uuid}` | Soft-delete trip |
| POST | `/api/v1/ambulance-trips/{uuid}/dispatch` | Dispatch ambulance |
| POST | `/api/v1/ambulance-trips/{uuid}/en-route` | Mark en route |
| POST | `/api/v1/ambulance-trips/{uuid}/on-scene` | Mark on scene |
| POST | `/api/v1/ambulance-trips/{uuid}/patient-contact` | Patient loaded |
| POST | `/api/v1/ambulance-trips/{uuid}/depart-scene` | Departing with patient |
| POST | `/api/v1/ambulance-trips/{uuid}/at-destination` | Arrived at destination |
| POST | `/api/v1/ambulance-trips/{uuid}/complete` | Complete trip |
| POST | `/api/v1/ambulance-trips/{uuid}/cancel` | Cancel trip |
| GET | `/api/v1/ambulance-trips/patient/{patientId}` | Trips for a patient |
| GET | `/api/v1/ambulance-trips/from-facility/{facilityId}` | Trips from a facility |
| GET | `/api/v1/ambulance-trips/to-facility/{facilityId}` | Trips to a facility |
| GET | `/api/v1/ambulance-trips/active` | Currently active trips |

### Ambulance Crew
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/ambulance-crew` | List all crew assignments |
| POST | `/api/v1/ambulance-crew` | Assign staff to ambulance |
| PUT | `/api/v1/ambulance-crew/{id}` | Update assignment |
| DELETE | `/api/v1/ambulance-crew/{id}` | Remove assignment |
| GET | `/api/v1/ambulance-crew/ambulance/{ambulanceId}` | Crew for a vehicle |
| GET | `/api/v1/ambulance-crew/staff/{staffId}` | Vehicles for a staff |

### Ambulance Trip Logs
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/ambulance-trips/{tripUuid}/logs` | Logs for a trip |
| POST | `/api/v1/ambulance-trips/{tripUuid}/logs` | Add log entry |

---

## 6. Filtering & Search (All List Endpoints)

- `status`, `priority`, `trip_type`, `vehicle_type`
- `facility_id`, `patient_id`, `ambulance_id`
- `from_date`, `to_date` (by created_at or dispatched_at)
- `search` (by notes, patient name, vehicle identifier)
- `per_page` (pagination)

---

## 7. File Structure

```
database/migrations/
├── 2026_05_14_xxxxxx_create_ambulances_table.php
├── 2026_05_14_xxxxxx_create_ambulance_crew_members_table.php
├── 2026_05_14_xxxxxx_create_ambulance_trips_table.php
├── 2026_05_14_xxxxxx_create_ambulance_trip_logs_table.php

app/Models/
├── Ambulance.php
├── AmbulanceCrewMember.php
├── AmbulanceTrip.php
├── AmbulanceTripLog.php

app/Repositories/Interfaces/
├── AmbulanceRepositoryInterface.php
├── AmbulanceTripRepositoryInterface.php
├── AmbulanceCrewMemberRepositoryInterface.php
├── AmbulanceTripLogRepositoryInterface.php

app/Repositories/
├── AmbulanceRepository.php
├── AmbulanceTripRepository.php
├── AmbulanceCrewMemberRepository.php
├── AmbulanceTripLogRepository.php

app/Services/Interfaces/
├── AmbulanceServiceInterface.php
├── AmbulanceTripServiceInterface.php
├── AmbulanceCrewMemberServiceInterface.php
├── AmbulanceTripLogServiceInterface.php

app/Services/
├── AmbulanceService.php
├── AmbulanceTripService.php
├── AmbulanceCrewMemberService.php
├── AmbulanceTripLogService.php

app/Http/Controllers/Api/
├── AmbulanceController.php
├── AmbulanceTripController.php
├── AmbulanceCrewMemberController.php
├── AmbulanceTripLogController.php

app/Http/Requests/
├── Ambulance/
│   ├── StoreAmbulanceRequest.php
│   └── UpdateAmbulanceRequest.php
├── AmbulanceTrip/
│   ├── StoreAmbulanceTripRequest.php
│   └── UpdateAmbulanceTripRequest.php
├── AmbulanceCrewMember/
│   ├── StoreAmbulanceCrewMemberRequest.php
│   └── UpdateAmbulanceCrewMemberRequest.php
├── AmbulanceTripLog/
│   └── StoreAmbulanceTripLogRequest.php

app/Http/Resources/
├── AmbulanceResource.php
├── AmbulanceCollection.php
├── AmbulanceTripResource.php
├── AmbulanceTripCollection.php
├── AmbulanceCrewMemberResource.php
├── AmbulanceCrewMemberCollection.php
├── AmbulanceTripLogResource.php
├── AmbulanceTripLogCollection.php

routes/api_v1/
├── ambulances/_index.php
├── ambulanceTrips/_index.php
├── ambulanceCrew/_index.php

app/Providers/
├── AmbulanceServiceProvider.php  (binds all 4 pairs)

database/factories/
├── AmbulanceFactory.php
├── AmbulanceTripFactory.php
├── AmbulanceCrewMemberFactory.php
├── AmbulanceTripLogFactory.php
```

---

## 8. Implementation Strategy

### Phase 1 — Core (Ambulance + Trip)
1. Migrations: ambulances, ambulance_trips
2. Models with relationships
3. Repository interfaces + implementations
4. Service interfaces + implementations
5. Controllers (CRUD + status transitions)
6. Resources + Collections
7. Routes
8. Service Provider binding
9. Factories

### Phase 2 — Crew Management
1. Migration: ambulance_crew_members
2. Model, Repo, Service, Controller, Resource, Routes, Factory

### Phase 3 — Trip Logging
1. Migration: ambulance_trip_logs
2. Model, Repo, Service, Controller, Resource, Routes, Factory

### Phase 4 — Polish
- Add ambulance stats/analytics
- Add trip duration calculations
- Add billing code suggestions based on trip_type and mileage
- Integration with Visit module (link trip → visit)

---

## 9. Patterns Followed (Existing Convention)

| Layer | Pattern | Example |
|-------|---------|---------|
| Repository Interface | Contract per entity | `AmbulanceRepositoryInterface` |
| Repository | Eloquent implementation | `AmbulanceRepository implements AmbulanceRepositoryInterface` |
| Service Interface | Business logic contract | `AmbulanceServiceInterface` |
| Service | Delegates to repository, returns resources | `AmbulanceService implements AmbulanceServiceInterface` |
| Controller | Thin — delegates to service | `AmbulanceController` |
| Resource | `whenLoaded()` for relationships | `AmbulanceResource` |
| Validation | Per-action FormRequest | `StoreAmbulanceRequest`, `UpdateAmbulanceRequest` |
| Service Provider | Dedicated provider, bound in `config/app.php` | `AmbulanceServiceProvider` |
| Routes | Grouped under `auth:sanctum` in `routes/api_v1/{module}/_index.php` | `routes/api_v1/ambulances/_index.php` |

---

## 10. Open Questions for Your Review

1. **Trip vs Dispatch naming** — I used "Trip" as it covers the full lifecycle from request to completion. Do you prefer "Dispatch" instead?(Answer:Lets us use trip.)

2. **Crew assignment model** — Should crew be a simple pivot (ambulance_id + staff_id + role) or a full entity with its own endpoints? I proposed a full entity for flexibility.(Answer,lets us go with full entity)

3. **Billing** — Do you want billing integration in Phase 1 (trip has a charge_amount field) or defer to the existing billing module?(Answer:Lets leave out billing for now and defer to existing billing module)

4. **Trip logs** — Needed in Phase 1 or can wait? Useful for audit/EMT documentation but adds scope.(Answer: We can add trip logs.)

5. **Ambulance type enum values** — I used US-standard types (Type I, II, III). Should I align to local/regional standards instead?(Lets use both standards)

6. **Vehicle tracking** — Do you need GPS coordinate tracking (real-time lat/lng per trip) or is manual status update sufficient for now?(Manual status is enough for now.)

---

*Review and approve/modify before implementation begins.*
