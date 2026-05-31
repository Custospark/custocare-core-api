# Subscription Lifecycle

## Fields

| Field | Type | Purpose |
|-------|------|---------|
| `status` | enum (`trial`, `active`, `past_due`, `suspended`, `cancelled`) | Current lifecycle state |
| `plan_id` | FK → plans | Current plan |
| `billing_cycle` | `monthly` / `yearly` | Billing frequency |
| `starts_at` | timestamp | When current period started |
| `ends_at` | timestamp | End of current billing period |
| `next_billing_date` | timestamp | When payment is next due |
| `trial_ends_at` | timestamp | **Set once, never cleared** |
| `grace_period_ends_at` | timestamp | **Set once, never cleared** |
| `suspended_at` | timestamp | When suspended |
| `approved_at` | timestamp | When payment was last approved by admin |
| `onboarding_fee_paid` | bool | Whether onboarding fee was paid |
| `metadata` | JSON | `cancel_at_period_end`, `pending_upgrade_plan_id`, `access_ends_at` |

## Immutable Markers

Two fields, once set, are **never overwritten with null** by any code path:

- **`trial_ends_at`** — set when a trial is first granted. Remains even after payment approval, plan switches, suspension, or cancellation. Used by `hasEverHadTrial()` to detect returning facilities and prevent second trials.

- **`grace_period_ends_at`** — set when a subscription first transitions to `past_due` with an active grace window. Remains even after payment approval, suspension, or resubscription. Used by `hasEverHadTrial` equivalent to prevent multiple grace periods.

Both are only conditionally included in update payloads (when their value is non-null). Setting to `null` is intentionally impossible through normal code paths.

## Transition Map

### 1. First Subscribe (new facility)

```
status:        null → trial
starts_at:     now
ends_at:       trial_end + billing_cycle
next_billing:  trial_end (first payment due when trial ends)
trial_ends_at: now + plan.trial_days  ← SET ONCE
grace:         null
```

Trigger: `POST /facilities/{facility}/subscription` → `SubscriptionService::createSubscription()`

---

### 2. Switch Plans Mid-Trial

```
plan_id:       new plan
billing_cycle: new cycle (from user toggle)
trial_ends_at: PRESERVED (unchanged)
```

Only `plan_id` and `billing_cycle` are updated. The trial clock keeps ticking — switching plans doesn't reset or extend the trial.

Trigger: `POST /facilities/{facility}/subscription` → `createSubscription()` → `$existing->hasAccess()` path.

---

### 3. Trial Expires (auto-transition)

Triggered by `getSubscriptionForFacility()` on the next API request after `trial_ends_at` or `next_billing_date` passes.

```
status:        trial → past_due
grace:         now + 7d  ← SET ONCE (only if null)
trial_ends_at: PRESERVED
```

If `grace_period_ends_at` is already set (returning facility), no new grace is granted.

---

### 4. Pay During Trial / Grace (admin approves)

```
status:        trial/past_due → active
starts_at:     now
ends_at:       now + billing_cycle
next_billing:  now + billing_cycle
approved_at:   now
suspended_at:  null
trial_ends_at: PRESERVED
grace:         PRESERVED
```

Trigger: Admin approves payment → `PaymentService::approvePayment()` → `activateSubscription()` or `renewSubscription()`.

---

### 5. Billing Date Passes (auto-transition)

Same as trial expiry but for active subscriptions:

```
status:        active → past_due
grace:         now + 7d  (only if never set before)
```

Trigger: `getSubscriptionForFacility()` checks `next_billing_date->isPast()`.

---

### 6. Grace Expires (auto-transition)

```
status:        past_due → suspended
suspended_at:  now
grace:         PRESERVED
```

Trigger: `getSubscriptionForFacility()` checks `grace_period_ends_at->isPast()`.

---

### 7. Resubscribe While Suspended

```
plan_id:       new plan
billing_cycle: new cycle
status:        SUSPENDED (stays suspended — no access granted)
trial_ends_at: PRESERVED
grace:         PRESERVED
```

The subscription stays `suspended`. No trial, no grace. Payment is the only way to reactivate.

Trigger: `POST /facilities/{facility}/subscription` → `createSubscription()` → `$isSuspended && $hasUsedGraceBefore` path.

---

### 8. Pay While Suspended (admin approves)

Same as #4. `status → active`, period dates recalculated from `now`.

---

### 9. Active Subscription Expires (billing passes)

Same as #5.

---

### 10. Cancel at Period End

```
metadata.cancel_at_period_end:  true
metadata.access_ends_at:        effective_at
status:                         active (until ends_at, then auto-suspend)
```

When `ends_at` passes and `cancel_at_period_end` is true, `hasAccess()` returns false and the auto-transition suspends.

---

## Auto-Transition Logic

Every API request that resolves a facility runs `SubscriptionService::getSubscriptionForFacility()`:

```php
// Step 1: Transition active/trial past their billing date
if (status === active || trial) {
    if (next_billing_date->isPast() || trial_ends_at->isPast()) {
        markPastDue();  // → past_due, grace only if first time
    }
}

// Step 2: Suspend past_due with expired grace
if (status === past_due) {
    if (grace_period_ends_at->isPast()) {
        suspend();  // → suspended
    }
}

// Step 3: Apply due scheduled plan changes
applyPendingScheduledChanges();  // plan_id updated, change record deleted
```

No cron job required — transitions happen on the first API request after the threshold is crossed.

## Frontend Payment Action Resolver

The backend `SubscriptionPaymentActionResolver` determines `payment_action` returned in the subscription API response:

| Status | Condition | `required` | `intent` |
|--------|-----------|-----------|----------|
| Any | Pending payment exists | `false` | — |
| Any | `pending_upgrade_plan_id` in metadata | `true` | `upgrade_now` |
| Trial | Not approved yet, trial still active | `true` | `subscription` |
| Past_due | — | `true` | `renewal` |
| Active | — | `false` | — |
| Suspended | — | `false` | — |
| Cancelled | — | `false` | — |
