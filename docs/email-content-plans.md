# Transactional Email Content Plans

> Created: 2026-05-28
> Status: **IMPLEMENTED — 2026-05-28**

---

## How Emails Are Sent (Current Architecture)

All emails use a single generic `StandardEmail` mailable + a single Blade template (`resources/views/emails/standard.blade.php`). Email body is an HTML string built inside event Listeners, then sent via `NotificationService::sendToUser()`.

**Pattern:** Event → Listener (ShouldHandleEventsAfterCommit) → NotificationService::sendToUser() → Mail::to()->send(new StandardEmail(...))

The `StandardEmail` mailable supports:
- `ctaUrl` + `ctaLabel` — renders a clickable button in the email header area
- `tip` — renders a "pro tip" callout box

We set `ctaUrl` to the login link and `ctaLabel` to "Log In to Custocare" in all 4 emails for consistency.

### Global branding in the email template

The header and footer in `resources/views/emails/standard.blade.php` display consistently across all emails:

**Header:**
> **Custocare**
> Continuous Care. Clinical Excellence.
> A product of [Custospark Company Ltd](https://www.custospark.com) — PowerHouse of Innovations.

**Footer:**
> You're receiving this because you use **Custocare**, a product of [Custospark Company Ltd](https://www.custospark.com) — PowerHouse of Innovations.

These are baked into the Blade template — no per-email configuration needed.

---

## Email 1: Staff Registration — "Your Staff Number"

### Trigger
After `POST /staff` (StaffController::store) succeeds — i.e., `StaffService::createStaff()` returns a new Staff record.

### Recipient
The User linked via `staff.user_id`. Decrypt their `email_encrypted` (handled by NotificationService).

### Event Class
`App\Events\StaffRegistered`

### Listener Class
`App\Listeners\SendStaffRegisteredNotification`

### Subject
**Your Custocare Staff Number is Ready**

### CTA
- **Label:** Log In to Custocare
- **URL:** https://custocare.custospark.com/login

### Body (HTML, built in listener)

```
<p>Dear {first_name},</p>

<p>Welcome to <strong>Custocare</strong>. Your staff profile has been created and you're now part of our healthcare network.</p>

<p>Here is your unique <strong>Staff Number</strong>:</p>

<div style="...">
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; text-align: center;">
        {staff_uuid}
    </p>
</div>

<p><strong>What is this Number for?</strong></p>
<p>This Number is unique to you and tied to your professional profile. Share it with <strong>Health Facility Administrators</strong> so they can send you an invitation to join their clinical workspaces.</p>

<p><strong>How it works:</strong></p>
<ol>
    <li>Share your Staff Number with a verified Facility Administrator.</li>
    <li>They will send you an invitation to their facility's workspace.</li>
    <li>Accept the invitation to start collaborating.</li>
</ol>

<p><strong>Security tip:</strong> Only share your Staff Number with trusted, verified facility administrators. Custocare staff will never ask for it unsolicited.</p>

<p>Ready to get started? Log in to your account at any time.</p>

<p>Warm regards,<br>Custocare Team</p>
```

### Registration in EventServiceProvider
```php
\App\Events\StaffRegistered::class => [
    \App\Listeners\SendStaffRegisteredNotification::class,
],
```

### Fire location
In `StaffController::store()`, after successful staff creation:
```php
event(new StaffRegistered($staff, $staff->user));
```

---

## Email 2: Facility Registration — "Welcome + Your Facility Number"

### Trigger
After `POST /facilities` (FacilityController::store) succeeds — i.e., `FacilityService::createFacilityByAdmin()` returns a new Facility record.

### Recipient
Send to both:
1. **Owner's user email** (via `NotificationService::sendToUser()` to the staff->user)
2. **Facility email** (facility has a direct `email` field on the model)

### Event Class
`App\Events\FacilityRegistered`

### Listener Class
`App\Listeners\SendFacilityRegisteredNotification`

### Subject
**Welcome to Custocare — Your Facility is Set Up**

### CTA
- **Label:** Log In to Custocare
- **URL:** https://custocare.custospark.com/login

### Body (HTML, built in listener)

```
<p>Dear {first_name},</p>

<p>Congratulations! Your healthcare facility <strong>{facility_name}</strong> has been successfully registered on Custocare.</p>

<p>Here is your <strong>Facility Number</strong>:</p>

<div style="...">
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; text-align: center;">
        {facility_code}
    </p>
</div>

<p>This is your facility's unique identifier. Keep it safe — you'll need it for:</p>
<ul>
    <li>Verifying your facility during support inquiries</li>
    <li>Billing and subscription management</li>
    <li>Staff onboarding — share with staff to link them to this facility</li>
</ul>

<p><strong>Next steps:</strong></p>
<ol>
    <li><strong>Set up services</strong> — Configure the clinical services your facility offers.</li>
    <li><strong>Review your subscription</strong> — Check your plan and billing details.</li>
</ol>

<p>If you have any questions, our support team is here to help.</p>

<p>Warm regards,<br>Custocare Team</p>
```

### Registration in EventServiceProvider
```php
\App\Events\FacilityRegistered::class => [
    \App\Listeners\SendFacilityRegisteredNotification::class,
],
```

### Fire location
In `FacilityController::store()`, after successful facility creation:
```php
// Resolve the owner
$staff = \App\Models\Staff::find($validatedData['user_id']);
$user = $staff?->user;
event(new FacilityRegistered($facility, $user));
```

Listener should also send to facility email if present:
```php
if ($event->facility->email) {
    Mail::to($event->facility->email)->send(...);
}
```

---

## Email 3: User Email Verified (First Time) — "Welcome to Custocare"

### Trigger
After `POST /auth/verify-email` succeeds AND this is the user's **first-time email verification** (i.e., the email was NOT already verified before this call).

### How to detect "first time"
In `AccountRecoveryService::verifyEmail()`, before calling `$user->markEmailAsVerified()`, check `!$user->hasVerifiedEmail()`. If true, fire `UserEmailVerified` after marking.

### Recipient
The user whose email was verified.

### Event Class
`App\Events\UserEmailVerified`

### Listener Class
`App\Listeners\SendUserWelcomeNotification`

### Subject
**Welcome to Custocare — Your Email is Verified**

### CTA
- **Label:** Log In to Custocare
- **URL:** https://custocare.custospark.com/login

### Body (HTML, built in listener)

```
<p>Dear {first_name},</p>

<p>Welcome to <strong>Custocare</strong>! We're excited to have you on board.</p>

<p>Your email address has been verified and your account is now active. You can log in and start exploring what Custocare has to offer.</p>

<p><strong>What you can do now:</strong></p>
<ul>
    <li><strong>Complete your profile</strong> — Add your details to personalise your experience.</li>
    <li><strong>Register as a patient</strong> — Set up your patient portal to access health records and book appointments.</li>
    <li><strong>Register as a staff member</strong> — Join clinical workspaces and collaborate with healthcare teams.</li>
    <li><strong>Register a facility</strong> — Set up your healthcare facility on Custocare.</li>
</ul>

<p>Need help getting started? Contact our support team.</p>

<p>Warm regards,<br>Custocare Team</p>
```

> **Note:** This email is sent before the user chooses a role (before staff/patient/facility onboarding), so it's a general welcome with all options listed. No role-specific conditionals needed.

### Registration in EventServiceProvider
```php
\App\Events\UserEmailVerified::class => [
    \App\Listeners\SendUserWelcomeNotification::class,
],
```

### Fire location
In `AccountRecoveryService::verifyEmail()`, after `$user->markEmailAsVerified()`, only when email was NOT previously verified:
```php
if (!$user->hasVerifiedEmail()) {
    $user->markEmailAsVerified();
    UserEmailVerified::dispatch($user);
}
```

---

## Email 4: Patient Self-Registration — "Welcome to Your Patient Portal"

### Trigger
After `POST /patients` (PatientController::store) succeeds — BUT only when the patient registered themselves (NOT when created by an admin via `createPatientByAdmin`).

### How to distinguish self vs admin
Check in `PatientController::store()` by comparing `$validatedData['user_id']` with the authenticated user's ID. If they match → self-registration.

### Recipient
The User linked via `patient.user_id`.

### Event Class
`App\Events\PatientRegistered`

### Listener Class
`App\Listeners\SendPatientWelcomeNotification`

### Subject
**Welcome to Custocare — Your Patient Portal is Ready**

### CTA
- **Label:** Log In to Custocare
- **URL:** https://custocare.custospark.com/login

### Body (HTML, built in listener)

```
<p>Dear {first_name},</p>

<p>Welcome to <strong>Custocare</strong>! Your patient portal is now active and ready to use.</p>

<p>Your unique <strong>Patient Number</strong> is:</p>

<div style="...">
    <p style="font-size: 28px; font-weight: 700; letter-spacing: 4px; text-align: center;">
        {patient_uuid}
    </p>
</div>

<p>Keep this number handy — you may need it when visiting facilities or speaking with support. Share it with your healthcare providers so they can easily locate your records.</p>

<p>Here's what you can do with your portal:</p>
<ul>
    <li><strong>View your health records</strong> — Access your medical history, lab results, and prescriptions.</li>
    <li><strong>Book appointments</strong> — Schedule visits with your healthcare providers at your convenience.</li>
    <li><strong>Secure messaging</strong> — Communicate with your care team.</li>
    <li><strong>Manage prescriptions</strong> — View and request prescription refills.</li>
</ul>

<p><strong>Getting started:</strong></p>
<ol>
    <li>Log in to your account.</li>
    <li>Complete your health profile.</li>
    <li>Access your lab results, appointments, and medical history for all visits across all facilities.</li>
</ol>

<p>If you have any questions, our support team is happy to help.</p>

<p>Warm regards,<br>Custocare Team</p>
```

### Registration in EventServiceProvider
```php
\App\Events\PatientRegistered::class => [
    \App\Listeners\SendPatientWelcomeNotification::class,
],
```

### Fire location
In `PatientController::store()`, after successful patient creation, ONLY when self-registered:
```php
$patient = $this->patientService->createPatient($validatedData);

// Fire welcome email only for self-registration
if ((int) $validatedData['user_id'] === Auth::id()) {
    event(new PatientRegistered($patient, $patient->user));
}
```

---

## Implementation Summary Table

| # | Event | Listener | Fire In | Condition |
|---|-------|----------|---------|-----------|
| 1 | `StaffRegistered` | `SendStaffRegisteredNotification` | `StaffController::store()` | Always after creation |
| 2 | `FacilityRegistered` | `SendFacilityRegisteredNotification` | `FacilityController::store()` | Always after creation; also send to facility email |
| 3 | `UserEmailVerified` | `SendUserWelcomeNotification` | `AccountRecoveryService::verifyEmail()` | Only first-time verification |
| 4 | `PatientRegistered` | `SendPatientWelcomeNotification` | `PatientController::store()` | Only when `user_id === Auth::id()` |

All 4 emails use `NotificationService::sendToUser()` with `channel: 'email'` and pass `ctaUrl: 'https://custocare.custospark.com/login'` + `ctaLabel: 'Log In to Custocare'` to the StandardEmail mailable.

---

## API Endpoints Reference (UUIDs sent in these emails)

| Email | Entity | Public-facing term | Field | Example Value |
|-------|--------|--------------------|-------|---------------|
| Staff | Staff | Staff Number | `staff_uuid` | `ST-01HZX9F7KXYZ` |
| Facility | Facility | Facility Number | `facility_code` | `HF-H101HZX9F7K` |
| Patient | Patient | Patient Number | `patient_uuid` | `PT-01HZX9F7KXYZ` |

---

## Addressable nuances

### Naming consistency
All three public-facing identifiers use **"Number"** — Staff Number, Facility Number, Patient Number. The subject lines and body copy are consistent across all four emails.

### CTA button
The `StandardEmail` mailable accepts `ctaUrl` and `ctaLabel`. We pass `https://custocare.custospark.com/login` and `"Log In to Custocare"` in all emails so the email header renders a prominent button.

### Welcome email is role-agnostic
Email 3 (User Email Verified) fires before the user picks a role (staff/patient/facility), so it lists all options without conditionals. This avoids the listener needing to know what the user will do next.

### Facility goes to both inboxes
Facility email is sent to the owner's user email AND the facility's direct email (if present), ensuring the facility's general inbox also gets the credentials.

### Patient welcome is self-registration only
Admin-created patients already have the admin handling the onboarding, so no welcome email — avoids double-notification.
