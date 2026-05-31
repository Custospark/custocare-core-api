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

---

## Email 5: Subscription Trial Started

### Trigger
After `POST /facilities/{facility}/subscription` (SubscriptionController::store) succeeds AND the subscription status is `trial`.

### How to detect
Check `$subscription->status === 'trial'` after `createSubscription()` returns.

### Recipient
All facility owners (via `NotificationService::sendBillingToFacility()`).

### Fire location
In `SubscriptionController::store()`, after successful subscription creation:
```php
if ($subscription->status === SubscriptionStatus::TRIAL) {
    event(new SubscriptionTrialStarted($subscription));
}
```

### Event Class
`App\Events\Billing\SubscriptionTrialStarted`

### Listener Class
`App\Listeners\SendSubscriptionTrialStartedNotification`

### Subject
**Your {plan_name} Trial Has Started — Welcome to Custocare**

### CTA
- **Label:** View Subscription
- **URL:** `{app_url}/admin/plans-subscriptions`

### Body (HTML, built in listener)

| Placeholder | Source |
|-------------|--------|
| `{facility_name}` | `$subscription->facility->facility_name` |
| `{plan_name}` | `$subscription->plan->name` |
| `{trial_days}` | `$plan->trial_days` |
| `{trial_end_date}` | formatted `$subscription->trial_ends_at` |
| `{billing_cycle}` | `$subscription->billing_cycle` |
| `{plan_price}` | formatted `$plan->price_usd` |

```
<p>Dear Facility Administrator,</p>

<p>Your <strong>{plan_name}</strong> subscription for <strong>{facility_name}</strong> is now active and your {trial_days}-day free trial has begun.</p>

<div style="background-color: #f0f9ff; border-left: 4px solid #2563eb; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px;"><strong>Trial ends:</strong> {trial_end_date}</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Plan:</strong> {plan_name} — ${plan_price}/mo ({billing_cycle} billing)</p>
</div>

<p>During this trial period, you have full access to all features included in your plan. Here's what to expect:</p>

<ul>
    <li><strong>Full access</strong> — All {plan_name} features are available for your facility.</li>
    <li><strong>No charges yet</strong> — Your first payment will be due on {trial_end_date}.</li>
    <li><strong>Switch anytime</strong> — You can upgrade or downgrade your plan before the trial ends.</li>
</ul>

<p>Before your trial ends, you'll need to submit a payment proof to continue uninterrupted access. We'll send you a reminder a few days before.</p>

<p>If you have any questions, our support team is here to help.</p>

<p>Warm regards,<br>Custocare Team</p>
```

---

## Email 6: Trial Ending Soon (2 Days Remaining)

### Trigger
In `SubscriptionService::getSubscriptionForFacility()`, when `trial_ends_at` is exactly 2 days from `now()`. Sent once per subscription lifecycle.

### How to detect
```php
$trialEndsAt = $subscription->trial_ends_at;
if ($subscription->status === SubscriptionStatus::TRIAL && $trialEndsAt && $trialEndsAt->isFuture()) {
    $daysLeft = (int) $now->diffInDays($trialEndsAt);
    if ($daysLeft === 2 && !$this->notificationSent($subscription, 'trial_ending_soon')) {
        // send email + mark sent
    }
}
```

### Recipient
All facility owners (via `NotificationService::sendBillingToFacility()`).

### Fire location
In `SubscriptionService::getSubscriptionForFacility()` → `sendBillingNotifications()` (NEW method), after auto-transition checks.

### Event / Listener
Called inline from `SubscriptionService` (no event/listener needed — it's a service concern).

### Subject
**Your {plan_name} Trial Ends in 2 Days — Complete Payment to Stay Active**

### CTA
- **Label:** Complete Payment
- **URL:** `{app_url}/admin/plans-subscriptions/payments`

### Body

```
<p>Dear Facility Administrator,</p>

<p>Your <strong>{plan_name}</strong> trial for <strong>{facility_name}</strong> ends on <strong>{trial_end_date}</strong> — that's just 2 days away.</p>

<p>To keep your facility running without interruption, please complete your payment before the trial ends.</p>

<div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px;"><strong>Plan:</strong> {plan_name}</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Amount due:</strong> ${plan_price}/{billing_cycle}</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Due date:</strong> {trial_end_date}</p>
</div>

<p><strong>How to pay:</strong></p>
<ol>
    <li>Log in to your Custocare account.</li>
    <li>Navigate to the Payments page under Plans & Subscriptions.</li>
    <li>Choose Bank Transfer as your payment method.</li>
    <li>Upload your payment receipt and submit for review.</li>
</ol>

<p>Need to change your plan before committing? You can upgrade or downgrade at any time during your trial — visit the Plans page to compare options.</p>

<p>If you have any questions, our support team is happy to assist.</p>

<p>Warm regards,<br>Custocare Team</p>
```

---

## Email 7: Grace Period Started (First Day Past Due)

### Trigger
In `SubscriptionService::markPastDue()`, when `grace_period_ends_at` is set and this is the first past_due transition (not a resubscribe). Sent once per subscription lifecycle.

### How to detect
The `markPastDue()` method already knows whether grace is being set (`$graceEndsAt !== null`) — this is the first-time grace grant. Only send when grace is actually granted.

### Recipient
All facility owners (via `NotificationService::sendBillingToFacility()`).

### Fire location
In `SubscriptionService::markPastDue()`, after the update, only when `$graceEndsAt !== null`:
```php
if ($graceEndsAt !== null) {
    $this->notificationService->sendBillingToFacility($subscription->facility, ...);
}
```

### Subject
**Payment Required — Your {plan_name} Subscription Is Now Past Due**

### CTA
- **Label:** Make Payment
- **URL:** `{app_url}/admin/plans-subscriptions/payments`

### Body

```
<p>Dear Facility Administrator,</p>

<p>The billing date for your <strong>{plan_name}</strong> subscription at <strong>{facility_name}</strong> has passed.</p>

<p>Don't worry — your facility still has full access. We've started a <strong>7-day grace period</strong> to give you time to complete your payment.</p>

<div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px;"><strong>Grace period ends:</strong> {grace_end_date}</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Amount due:</strong> ${plan_price}</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Status:</strong> Past due — action required</p>
</div>

<p>If payment is not received by {grace_end_date}, your subscription will be suspended and your facility will lose access to Custocare.</p>

<p><strong>To complete your payment:</strong></p>
<ol>
    <li>Log in to your Custocare account.</li>
    <li>Go to Plans & Subscriptions → Payments.</li>
    <li>Transfer the amount due to our bank account (details provided on the payments page).</li>
    <li>Upload your payment receipt and submit for review.</li>
</ol>

<p>Need to change your plan? You can still upgrade or downgrade during the grace period — no interruption to your service.</p>

<p>If you have any questions, please contact our support team.</p>

<p>Warm regards,<br>Custocare Team</p>
```

---

## Email 8: Grace Period Last Day (Final Reminder)

### Trigger
In `SubscriptionService::getSubscriptionForFacility()`, when `grace_period_ends_at` is tomorrow. Sent once per grace period.

### How to detect
```php
if ($subscription->status === SubscriptionStatus::PAST_DUE && $graceEndsAt && $graceEndsAt->isFuture()) {
    $daysLeft = (int) $now->diffInDays($graceEndsAt);
    if ($daysLeft === 1 && !$this->notificationSent($subscription, 'grace_last_day')) {
        // send email + mark sent
    }
}
```

### Recipient
All facility owners (via `NotificationService::sendBillingToFacility()`).

### Fire location
In `SubscriptionService::getSubscriptionForFacility()` → `sendBillingNotifications()` method.

### Subject
**Final Reminder — Your Grace Period Ends Tomorrow**

### CTA
- **Label:** Pay Now — Avoid Suspension
- **URL:** `{app_url}/admin/plans-subscriptions/payments`

### Body

```
<p>Dear Facility Administrator,</p>

<p>This is a final reminder that your <strong>{plan_name}</strong> grace period for <strong>{facility_name}</strong> ends <strong>tomorrow, {grace_end_date}</strong>.</p>

<div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px;"><strong>Action required:</strong> Complete payment by {grace_end_date}</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>After this date:</strong> Your subscription will be suspended and facility access will be blocked.</p>
</div>

<p><strong>What happens after suspension?</strong></p>
<ul>
    <li>Your facility staff will not be able to access Custocare.</li>
    <li>All patient data remains securely stored and preserved.</li>
    <li>You can restore access at any time by submitting a payment proof.</li>
</ul>

<p>Don't lose access — complete your payment today. The process takes just a few minutes.</p>

<p>If you've already submitted a payment, please disregard this message. Payments are reviewed and approved by our team during business hours.</p>

<p>Warm regards,<br>Custocare Team</p>
```

---

## Email 9: Subscription Suspended

### Trigger
In `SubscriptionService::suspendSubscription()`, after the update. Sent once per suspension event.

### Recipient
All facility owners (via `NotificationService::sendBillingToFacility()`).

### Fire location
In `SubscriptionService::suspendSubscription()`, after the update:
```php
$this->notificationService->sendBillingToFacility($subscription->facility, ...);
```

### Subject
**Your {plan_name} Subscription Has Been Suspended**

### CTA
- **Label:** Reactivate Subscription
- **URL:** `{app_url}/admin/plans-subscriptions/payments`

### Body

```
<p>Dear Facility Administrator,</p>

<p>Your <strong>{plan_name}</strong> subscription for <strong>{facility_name}</strong> has been suspended because the grace period ended on <strong>{grace_end_date}</strong> without a completed payment.</p>

<div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 16px; margin: 20px 0;">
    <p style="margin: 0; font-size: 14px;"><strong>Status:</strong> Suspended</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Access:</strong> Facility staff cannot currently use Custocare.</p>
    <p style="margin: 4px 0 0; font-size: 14px;"><strong>Data:</strong> All patient and facility data is preserved and secure.</p>
</div>

<p><strong>How to restore access:</strong></p>
<ol>
    <li>Log in to your Custocare account.</li>
    <li>Navigate to Plans & Subscriptions → Payments.</li>
    <li>Submit a payment proof for the amount due.</li>
    <li>Once approved by our team, your subscription will be reactivated immediately.</li>
</ol>

<p>All your facility data — patient records, clinical notes, configurations — remains intact and will be accessible again as soon as your subscription is reactivated.</p>

<p>If you believe this suspension is in error or need assistance, please contact our support team.</p>

<p>Warm regards,<br>Custocare Team</p>
```

---

## Implementation Summary Table (Subscriptions)

| # | Email | Trigger | Fire Location | Condition |
|---|-------|---------|---------------|-----------|
| 5 | Trial started | Subscription created with `status = trial` | `SubscriptionController::store()` | After `createSubscription()`, status is `trial` |
| 6 | Trial ending soon | `trial_ends_at` is 2 days from now | `SubscriptionService::sendBillingNotifications()` | Not yet sent for this event |
| 7 | Grace started | First `markPastDue()` with grace granted | `SubscriptionService::markPastDue()` | `$graceEndsAt !== null` |
| 8 | Grace last day | `grace_period_ends_at` is tomorrow | `SubscriptionService::sendBillingNotifications()` | Not yet sent for this event |
| 9 | Subscription suspended | `suspendSubscription()` called | `SubscriptionService::suspendSubscription()` | Always on suspension |

All emails use `NotificationService::sendBillingToFacility()` with the `StandardEmail` mailable. The `tip` field is not used in these emails — the body content is self-contained. A deduplication mechanism (checking metadata for sent flags) prevents duplicate sends across multiple requests (since auto-transition runs on every API request).
