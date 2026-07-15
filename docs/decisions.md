# Architecture Decision Records

> Each entry records a design decision, its context, and the trade-offs considered.
> Read this before starting any feature to avoid repeating past mistakes.

---

## 2026-05-22: ANY_VALUE → MAX — MariaDB Compatibility for Staff Forwarding Query

**Context:** The `getStaffForPatientForwarding()` endpoint in `VisitController.php` used `ANY_VALUE(vcounts.current_patient_count)` to suppress MySQL's `ONLY_FULL_GROUP_BY` error. `ANY_VALUE()` is a MySQL 5.7+ function that does not exist in MariaDB. The endpoint worked locally (MySQL) but crashed on production (MariaDB) with `SQLSTATE[42000]: 1305 FUNCTION ... ANY_VALUE does not exist`.

**Decision:**
- Replaced `ANY_VALUE(vcounts.current_patient_count)` with `MAX(vcounts.current_patient_count)`.
- `MAX()` is a standard SQL aggregate function available in all MySQL versions and MariaDB.
- The subquery already groups by `assigned_staff_id` (one row per staff), so `MAX()` returns the same single value as `ANY_VALUE()` — no behavioral difference.

**Files changed (BE — 1 file):**
- `VisitController.php:1019` — `ANY_VALUE(...)` → `MAX(...)`

**Trade-offs:**
- `MAX()` is semantically different from `ANY_VALUE()` — if the subquery ever returned multiple rows per staff (e.g., due to a change in the subquery grouping), `MAX()` would return the highest value while `ANY_VALUE()` would return an arbitrary one. For the current single-row-per-staff subquery, they're identical.
- No other `ANY_VALUE()` calls existed in the codebase (confirmed via grep).

---

## 2026-05-18: Team Structure Mirrored from Frontend

**Context:** The Backend project needs the same sub-agent team, handoff chain, and quality gates as the Frontend to ensure consistent execution across both stacks.

**Decision:**
- Adopted the same 5-agent team: Sage (Planning), Blue (Architect), Rex (Code), Vera (Test), Quill (Docs)
- Same handoff chain: Mike → Sage → Blue* → Rex → Vera → Quill → Mike → Oscar
- Blue skipped for changes ≤2 files; Sage hands directly to Rex
- Added Retro step after every feature to catch recurring issues before they compound
- Added Branch & PR Workflow section for when collaborators join
- Added Pre-Commit Hooks section (PHP syntax check via `php -l` on staged files)

**Trade-offs:**
- Identical structure across FE and BE means slightly different pre-commit tooling (eslint vs php -l) but the same mental model
- Retro adds ~30s per feature but prevents compounding design debt

---

## 2026-05-18: Pre-Commit Hooks via Husky + Lint-Staged

**Context:** Vera catches issues before commits but is manual. Automating syntax checks on every commit prevents trivial errors from reaching the remote.

**Decision:**
- Installed `husky` + `lint-staged` in the Backend project
- Pre-commit hook runs `php -l` on staged `.php` files
- Hook does NOT run full PHPUnit or migration tests (too slow for pre-commit — those remain Vera's job)

**Trade-offs:**
- `php -l` only catches parse errors, not logic bugs — but that's the right scope for a pre-commit fast guard
- Full test suite stays in Vera's domain; pre-commit is purely a syntax gate

---

## 2026-05-20: Added isStaff() Method to User Model

**Context:** `GET /api/v1/patients/{patient}/medical-history` returned a 500 error with `Call to undefined method App\Models\User::isStaff()`. The error occurred in `PatientPolicy::view()` (line 31) and `UpdatePatientRequest` (line 93), which both called `$user->isStaff()`.

The `staff()` relationship already existed on the User model, and the rest of the codebase already used `$user->staff` / `$user->staff()->exists()` directly — but the two policy/request files used a convenience method that hadn't been defined.

**Decision:**
- Added `isStaff(): bool` method to `App\Models\User` that delegates to `$this->staff()->exists()`
- Placed it immediately after the `staff()` relationship for logical grouping
- Follows the same pattern as the existing `isIdentityVerified()` and `isAccountLocked()` methods

**Trade-offs:**
- `staff()->exists()` is slightly more efficient than `$this->staff !== null` (issues COUNT query vs loading the model) when the relation isn't eager-loaded
- No existing tests broke because the method simply didn't exist before — all three call sites would have failed identically

---

## 2026-05-20: isStaff() Now Checks Active Facility Assignment; Added hasPermission()

**Context:** The initial `isStaff()` implementation only checked `staff()->exists()`, which returned true even for staff without any active facility assignment (e.g., terminated or suspended). Additionally, `PatientPolicy` and ~220 other policy methods called `$user->hasPermission('view_patients')` which didn't exist — Spatie's trait provides `hasPermissionTo()` not `hasPermission()`.

**Decisions:**
1. **`isStaff()` now requires an active or on-leave `FacilityStaffRole` assignment.** The method chains through `staff()->whereHas('facilityStaffRoles', ...)` checking `assignment_status IN ('active', 'on_leave')`. A staff record alone is no longer sufficient — there must be at least one valid facility assignment.
2. **Added `hasPermission(string $permission): bool`** to User model that delegates to Spatie's `hasPermissionTo()`. This resolves the 500 on medical history without touching any of the ~30 policy files that use the `hasPermission()` naming convention.

**Trade-offs:**
- `whereHas('facilityStaffRoles')` adds a JOIN/EXISTS query to every `isStaff()` call, but staff context is typically resolved once per request via middleware
- `hasPermission()` delegates to `isStaff()` instead of Spatie — actively assigned staff are granted permissions; role-level refinement is handled by `hasRole()` via FacilityStaffRole.role_code

---

## 2026-05-20: Removed Spatie Traits; hasPermission()/hasRole() Use FacilityStaffRole

**Context:** The previous `hasPermission()` delegated to `hasPermissionTo()` from Spatie's `HasPermissions` trait. Oscar directed to remove Spatie entirely and rely on facility assignment for authorization.

**Decisions:**
1. **Removed `HasRoles` and `HasPermissions` traits** from User model, along with Spatie imports.
2. **`hasPermission()` now returns `$this->isStaff()`** — actively assigned staff are granted permissions. No Spatie involvement.
3. **`hasRole(string|array $roles)`** queries `FacilityStaffRole.role_code` with `assignment_status IN ('active', 'on_leave')`. Replaces Spatie's role check.
4. **`hasAnyRole(array $roles)`** delegates to `hasRole()`. Used by `EnsureAdminAccess` middleware and AuditLogPolicy.
5. **`isStaff()`** unchanged — checks staff record + at least one active/on-leave facility assignment.

**Trade-offs:**
- `hasPermission()` is now coarse (true for all active staff). Role-level refinement is handled by `hasRole()` which was already the paired check in most policies.
- `assignRole()` / `removeRole()` in UserRepository will need separate refactoring (admin-only feature, not in medical history path).

---

## 2026-05-21: Visit Lifecycle Changes — Billing No Longer Completes Visits, Fully-Paid Excluded from Reuse

**Context:** Three bugs in the visit lifecycle and clinical data scoping chain:

1. **Billing auto-completed visits** — Full payment set `status = 'completed'` and `current_phase = 'discharged'`, preventing further clinical work on the same visit (medication, lab, etc.).
2. **Fully-paid visits reused** — When creating a new visit, the system reused existing `active` visits without checking `payment_status`, so a fully-paid visit could be silently reused.
3. **No visit-scoped prescription endpoint** — Frontend was fetching all patient prescriptions and filtering in-memory, which was inefficient and could pull draft data from previous visits.

**Decisions:**

**1. BillingProcessor.php — Remove visit completion on full payment**
- Removed `$visit->status = 'completed'` and `$visit->current_phase = 'discharged'` after successful payment processing.
- Visit stays `active` with phase `billing` after payment.
- Staff can still perform clinical actions on that visit.

**2. BillingService.php — Updated response message**
- Changed from "Payment successfully settled. Visit has been completed." to "Payment successfully settled." — reflects that the visit is no longer auto-completed.

**3. VisitService.php — Exclude fully-paid visits from reuse**
- Added `->where('payment_status', '!=', 'paid_in_full')` to the existing-visit query chain.
- Combined with existing `status = 'active'` and strict same-facility scoping.
- A fully-paid visit will never be silently reused; a new visit record is created instead.

**4. PrescriptionController.php — New visit-scoped endpoint**
- Added `visitPrescriptions(int $visitId)` method returning prescriptions filtered by `visit_id`.
- Route: `GET /api/v1/prescriptions/visit/{visitId}`.
- `PrescriptionResource.php`: Added `visit_id` and `patient_id` to serialized output.

**Files changed:**
- `app/Services/Billing/Processing/BillingProcessor.php` — removed status change
- `app/Services/Billing/BillingService.php` — updated response message
- `app/Services/Visit/VisitService.php` — added payment_status filter
- `app/Http/Controllers/Api/PrescriptionController.php` — new method
- `app/Http/Resources/PrescriptionResource.php` — added visit_id, patient_id

**Trade-offs:**
- Visit stays in `billing` phase after payment — no automatic phase progression. If auto-progression to a "post-billing" phase is needed later, it should be an explicit workflow step, not a side effect of payment processing.
- The `payment_status != 'paid_in_full'` filter is strict — even a visit with a full refund that was re-paid would have `paid_in_full` status and be excluded from reuse. In practice this is correct because a fully-paid visit should not be reused regardless of payment history.

---

## 2026-05-20: Prescription Template — Validate dosage_form/dosage_unit/duration_unit on Store; Defaults on applyTemplate

**Context:** Creating a prescription from a template failed because template items stored without `dosage_form`, `dosage_unit`, `duration_unit` were passed directly to `prescriptionItemRepository->createMany()`. The `StoreTemplateRequest` didn't validate these fields, and `applyTemplate()` didn't provide defaults.

**Decisions:**
1. Added `dosage_form`, `dosage_unit`, `duration_unit` to `StoreTemplateRequest` validation rules (all `required_with:default_medications`).
2. Added `??=` defaults in `PrescriptionService::applyTemplate()` loop for all required-but-occasionally-missing item fields: `dosage_form`, `dosage_unit`, `duration_unit`, `route`, `administration_instructions`, `refills`, `substitution`. This covers existing templates in the database.

**Files changed:** `StoreTemplateRequest.php`, `PrescriptionService.php`

**Trade-offs:**
- The `??=` defaults match the DB column defaults (`'Tablet'`, `'tablet(s)'`, `'Day(s)'`, etc.) — no behavioral change for properly populated templates.

---

## 2026-05-26: Subscription Billing — Remaining Trial Days Carried Forward on Activation

**Context:** `activateSubscription()` in `SubscriptionService.php` unconditionally set `next_billing_date = now + 1 month` when a facility paid during their trial period. This meant paying on day 10 of a 14-day trial lost the remaining 4 trial days — the facility paid for a full month but got only 20 days of access.

**Decision:**
- Calculate remaining trial days when activating: `$remainingTrialDays = $subscription->trial_ends_at && $subscription->trial_ends_at->isFuture() ? max(0, $now->diffInDays($subscription->trial_ends_at, false)) : 0`
- Set `$periodEnd = $now->copy()->addMonth()->addDays($remainingTrialDays)`
- This carries forward any unused trial time into the next billing period, so paying on day 10 of a 14-day trial results in `next_billing_date = now + 30 + 4 days`.

**Files changed (BE — 1 file):**
- `app/Services/Billing/SubscriptionService.php:139-143` — added remaining trial day calculation before setting `next_billing_date`

**Trade-offs:**
- `diffInDays(false)` returns a negative value if `trial_ends_at` is in the past, so `max(0, ...)` safely floors to 0 for expired trials — no behavioral change for the common case.
- The carry-forward only applies to the *initial* activation during a trial. Scheduled changes (upgrades/downgrades via `SubscriptionScheduledChangeService`) use `subscriptions.next_billing_date` directly and are unaffected by this logic.

---

## 2026-05-26: Billing Email Notifications — Activation, Payment Submission, Payment Approval

**Context:** Facility owners had no automated email notification when their subscription activated (trial started), when they submitted a payment proof, or when an admin approved their payment. Owners had to manually check the platform or wait for external communication.

**Decisions:**

1. **`NotificationService::sendBillingToFacility()`** — shared method that sends to the facility's direct email (`$facility->email`) if set, then queries facility owners via `facility_owners → staff → users` to get owner emails. Decrypts and deduplicates before sending.

2. **`StandardEmail` extended with `$fileAttachments`** — added `array $fileAttachments = []` constructor parameter. Each entry has `{data, name, mime}`. The `attachments()` method uses `Attachment::fromData()` to attach content in-memory — no temp files.

3. **PDF content generation methods** — `SubscriptionBillingPdfServiceInterface` gained `generateInvoicePdfContent(Invoice): string` and `generateReceiptPdfContent(Payment): string`, each returning raw PDF binary. Existing `downloadInvoicePdf()`/`downloadReceiptPdf()` were refactored to delegate to these new methods, keeping download behavior unchanged.

4. **Three trigger points:**
   - `SubscriptionService::activateSubscription()` — sends invoice PDF after activation
   - `PaymentService::recordPayment()` — sends confirmation (no attachment) after proof submission
   - `PaymentService::approvePayment()` — sends receipt PDF after admin approval

5. **Failure isolation** — email sending is wrapped in `try/catch` per recipient. A failed email logs the error but does not roll back the transaction (activation/approval still proceeds).

**Email content by event:**

| Event | Subject | Body includes | Attachment |
|-------|---------|--------------|------------|
| Trial activated | "Your [Plan] subscription is now active" | Plan name, facility name, invoice number | Invoice PDF |
| Payment submitted | "Payment proof received — pending review" | Amount, plan name, transaction reference | None |
| Payment approved | "Payment approved — receipt #[number]" | Amount, plan name, receipt number | Receipt PDF |

**Files changed:**
- `app/Mail/StandardEmail.php` — `$fileAttachments` param + `Attachment::fromData()` mapping
- `app/Services/Notification/NotificationService.php` — `sendBillingToFacility()` method
- `app/Services/Billing/Contracts/SubscriptionBillingPdfServiceInterface.php` — `generateInvoicePdfContent()`, `generateReceiptPdfContent()`
- `app/Services/Billing/SubscriptionBillingPdfService.php` — implementations + refactored download methods
- `app/Services/Billing/SubscriptionService.php` — injected notification + pdf services, post-activation email
- `app/Services/Billing/PaymentService.php` — injected notification + pdf services, post-submission and post-approval emails

**Trade-offs:**
- Owner emails are decrypted at send-time; any decryption failure drops that recipient but others still receive the email.
- PDF content is generated in-memory each time — no caching. For billing emails (low frequency), this is acceptable. If bulk-sending is needed later, a queued job with cached PDF content would reduce memory pressure.
- `facility_owners → staff → users` is a three-join path. For the current facility counts (<100 owners per facility), the query overhead is negligible.

---

## 2026-05-26: Billing Email Fixes — Invoice on Payment, Template Redesign, Upgrade/Schedule Emails

**Context:** Three issues were found after the initial billing email implementation:
1. The trial activation email accessed `$updated->invoice`, but `Subscription` has no `invoice` relationship — it's on `Payment`.
2. The email template header used a blue-to-emerald gradient that didn't match the Custospark design system.
3. Upgrade (`upgradeNow()`) and scheduled changes (`schedulePlanChange()`, `applyScheduledPlanChange()`) had no email notifications.

**Decisions:**

1. **Invoice lookup fixed** — Changed `$updated->invoice` to `$payment->loadMissing('invoice')` with `$payment->invoice`. The invoice is always linked to the payment that triggered activation, not the subscription.

2. **Template header redesigned** (`resources/views/emails/standard.blade.php`):
   - Removed the blue-to-emerald gradient header background
   - White background with "Custospark" in blue-500 (`#3b82f6`)
   - Single blue horizontal divider (`2px solid #3b82f6`) below brand
   - `h1` moved below the divider with a light gray top border
   - Brand text and tagline changed to gray
   - Logo border changed from white-on-white to `#e5e7eb` (visible on light backgrounds)
   - Body, CTA tip, and footer left untouched

3. **Three new email trigger points** — `upgradeNow()`, `schedulePlanChange()`, and `applyScheduledPlanChange()` now all send emails via the existing `NotificationService::sendBillingToFacility()` with the plan name in the subject. No PDF attachments for these — just a subject/body notification.

4. **`SubscriptionScheduledChangeService` injected `NotificationService`** as a constructor dependency (same pattern as `SubscriptionService` and `PaymentService`).

**Complete email flows:**

| Event | Subject | When | Attachment |
|-------|---------|------|------------|
| Subscription activated | "Your [Plan] subscription is now active" | Trial/initial payment approved | Invoice PDF |
| Payment submitted | "Payment proof received — pending review" | Facility uploads receipt | None |
| Payment approved | "Payment approved — receipt #[number]" | Admin approves payment | Receipt PDF |
| Upgrade (immediate) | "Your plan has been upgraded to [Plan]" | Upgrade now clicked | None |
| Schedule change | "Your [upgrade/plan change] to [Plan] has been scheduled" | Schedule confirmed | None |
| Scheduled change applied | "Your plan has been changed to [Plan]" | Effective date reached | None |

**Files changed:**
- `app/Services/Billing/SubscriptionService.php` — fixed `$updated->invoice` → `$payment->loadMissing('invoice')` / `$payment->invoice`; added email in `upgradeNow()`
- `resources/views/emails/standard.blade.php` — full header redesign
- `app/Services/Billing/SubscriptionScheduledChangeService.php` — injected `NotificationService`, added email calls in `schedulePlanChange()` and `applyScheduledPlanChange()`

**Trade-offs:**
- Upgrade and schedule emails carry no PDF attachment — no invoice/receipt is generated for plan changes. The plan name is embedded directly in the email body.
- Template redesign is purely CSS/HTML content within the existing Blade structure — no new variables or parameters added to `StandardEmail`.

---

## 2026-07-15: Auth Routes Bypass Subscription Check — Allow Login with Expired Subscription

**Context:** When a facility's subscription was suspended, the global `EnsureFacilitySubscriptionIsActive` middleware blocked `POST /api/auth/login` (and other auth routes) with a 403 error. Users with expired subscriptions couldn't log in at all — making it impossible to reach the subscription management pages to reactivate.

The middleware already whitelisted billing routes (`api/billing/plans*`, `api/facilities/*/subscription*`, `api/facilities/*/payments*`, etc.) so logged-in users could manage payments, but the login door was locked.

**Decision:**
- Added `'api/auth/*'` to `EnsureFacilitySubscriptionIsActive::$exceptPatterns`
- This allows all auth routes (login, register, forgot-password, verify-email, reset-password, logout, me) to bypass the subscription check
- Auth routes are user-level, not facility-level — subscription gating is irrelevant before authentication
- Once logged in, users can reach the already-whitelisted billing routes to reactivate their subscription

**Files changed (BE — 1 file):**
- `app/Http/Middleware/EnsureFacilitySubscriptionIsActive.php:34` — added `'api/auth/*'` to exception patterns

**Trade-offs:**
- `api/auth/me` (GET current user) and `api/auth/logout` are now accessible even with a suspended subscription — this is correct because the user needs to see their profile and log out
- No security concern: auth routes don't expose facility-scoped data that would be protected by a subscription

---

**Context:** Users who register through onboarding flows (staff, patient, facility) never received a follow-up email containing their unique identifier or a welcome message. The front-end shows the UUID on a success screen, but it's not persisted anywhere the user can refer back to.

**Decision:**
Added 4 event-listener pairs following the existing Event → Listener → NotificationService pattern:

| Event | Listener | Fires After | Condition |
|-------|----------|-------------|-----------|
| `StaffRegistered` | `SendStaffRegisteredNotification` | `POST /staff` | Always |
| `FacilityRegistered` | `SendFacilityRegisteredNotification` | `POST /facilities` | Always; also sends to facility email |
| `UserEmailVerified` | `SendUserWelcomeNotification` | `POST /auth/verify-email` | Only first-time verification |
| `PatientRegistered` | `SendPatientWelcomeNotification` | `POST /patients` | Only when `user_id === Auth::id()` |

**Key details:**
- Public-facing term for all identifiers is **"Number"** (Staff Number, Facility Number, Patient Number)
- All emails carry a CTA button pointing to `https://custocare.custospark.com/login`
- `NotificationService::sendToUser()` and `dispatchEmail()` extended with optional `$ctaUrl` / `$ctaLabel` params
- The `StandardEmail` mailable already supported CTA — just wasn't plumbed through NotificationService
- Tagline updated in `resources/views/emails/standard.blade.php`: *Continuous Care. Clinical Excellence.* with Custospark attribution: *PowerHouse of Innovations.*

**Files created (8):**
- `app/Events/StaffRegistered.php`
- `app/Events/FacilityRegistered.php`
- `app/Events/UserEmailVerified.php`
- `app/Events/PatientRegistered.php`
- `app/Listeners/SendStaffRegisteredNotification.php`
- `app/Listeners/SendFacilityRegisteredNotification.php`
- `app/Listeners/SendUserWelcomeNotification.php`
- `app/Listeners/SendPatientWelcomeNotification.php`

**Files changed (6):**
- `app/Providers/EventServiceProvider.php` — added 4 event-listener mappings
- `app/Http/Controllers/Api/StaffController.php` — dispatches `StaffRegistered`
- `app/Http/Controllers/Api/FacilityController.php` — dispatches `FacilityRegistered`
- `app/Http/Controllers/Api/PatientController.php` — dispatches `PatientRegistered` (self-registration only)
- `app/Services/User/AccountRecoveryService.php` — dispatches `UserEmailVerified` (first-time only)
- `app/Services/Notification/NotificationService.php` — added CTA passthrough
- `resources/views/emails/standard.blade.php` — updated tagline and Custospark branding

**Trade-offs:**
- Facility welcome is sent to both owner's user email and facility's direct email — avoids missing the facility's shared inbox
- Patient welcome is intentionally skipped for admin-created patients to avoid confusion (admin handles onboarding)
- User welcome (Email 3) is generic/role-agnostic since it fires before the user chooses a role — no conditional logic needed

---

## 2026-07-15: Inventory Bulk Import — Template Download + Chunked XLSX/CSV Upload

**Context:** Facilities needed a way to upload inventory items in bulk rather than creating them one-by-one through the form drawer. This mirrors the bulk product import pattern already implemented in Custosell.

**Decision:**
- Created `InventoryItemImportService` with:
  - `generateTemplate()` — generates an XLSX with headers matching key `InventoryItem` fields, an example row, and frozen header row
  - `import()` — reads XLSX/XLS/CSV via PhpSpreadsheet, validates each row with per-field rules, processes in chunks of 100 within DB transactions, creates an initial ledger entry for each item via `InventoryLedgerService`
- Created `InventoryItemImportController` with:
  - `GET /api/inventory-items/import-template` — downloads the template XLSX
  - `POST /api/inventory-items/import` — accepts file upload (max 20MB, xlsx/xls/csv), sets 10-min timeout and 512MB memory limit for large files
- Frontend `InventoryImportModal` follows the same pattern as Custosell's `ImportModal`: file picker with drag-and-drop zone, upload progress bar, template download button, results view with per-row error details
- Import button added to `InventoryItemHeader` next to the "New Item" button

**Template columns (25):**
`Item Name*`, `Item Code`, `Category*`, `Unit of Measure*`, `Package Qty*`, `Unit Cost`, `Currency Code*`, `Generic Name`, `Brand Name`, `NDC Code`, `Dosage Form`, `Strength`, `Route of Administration`, `Manufacturer`, `Supplier`, `Reorder Point`, `Reorder Qty`, `Safety Stock`, `Max Stock Level`, `Requires Prescription (Yes/No)`, `Requires Refrigeration (Yes/No)`, `Is Hazardous (Yes/No)`, `Is Billable (Yes/No)`, `Description`, `Status*`

`*` = required fields. Yes/No columns accept `yes` or `no` (case-insensitive).

**Files created (4):**
- `app/Services/InventoryItem/InventoryItemImportService.php` — core import logic
- `app/Http/Controllers/Api/InventoryItemImportController.php` — HTTP endpoints
- `src/renderer/.../components/InventoryImportModal.tsx` — FE import modal

**Files modified (3):**
- `routes/api_v1/inventoryItem/_index.php` — added import-template and import routes
- `src/renderer/.../AdminInventoryItems.tsx` — wired import modal
- `src/renderer/.../components/InventoryItemHeader.tsx` — added Import button

**Trade-offs:**
- Uses PhpSpreadsheet (new dependency `phpoffice/phpspreadsheet ^5.9`) — adds ~5 packages but is the same library used in Custosell
- Import is online-only — no offline support (same as Custosell)
- No validation on empty template rows (skips them silently)
- Category/dosage form/route values are normalized — invalid values fall back to defaults (`other` for category, `null` for dosage form/route, `active` for status)

---

## ADR-2026-05-29-1: Discharge Form Implementation — Embedded in Visit, No Separate Entity

**Status:** Accepted

**Context:** Need a discharge form UI for clinicians to process patient discharges and generate discharge summaries.

**Decision:**
1. **No separate Discharge entity** — discharge data lives on the existing `visits` table. No new table, model, or relationships needed.
2. **New column added** — `discharge_diagnosis` (TEXT, nullable) added to `visits` table for the final discharge diagnosis summary.
3. **Staff ID bug fixed** — `VisitController::discharge()` was passing `Auth::id()` (user_id) as `discharged_by_staff_id` instead of resolving the staff record. Fixed by using `Staff::where('user_id', ...)` pattern consistent with other controller methods.
4. **Frontend follows existing clinical form pattern** — Mode-based (idle/create/edit) with React Query hooks, BaseFormWrapper, BaseFormActions, sub-component directory, report launcher, and form grid tile registration.
5. **Dedicated Form Requests** — Validation moved from inline `$request->validate()` to `StoreDischargeRequest` and `UpdateDischargeRequest` classes.

**Consequences:**
- Discharge data is always accessible via the Visit model without joins
- VisitResource now includes `discharge_diagnosis` field
- Three API endpoints for discharge: GET (read), POST (initial discharge + status change), PUT (update after discharge)
- No changes to `bootstrap/providers.php` or VisitServiceProvider
