# Architecture Decision Records

> Each entry records a design decision, its context, and the trade-offs considered.
> Read this before starting any feature to avoid repeating past mistakes.

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
