# Reusable Payments Package — Implementation Plan (Custocare first)

Status: PROPOSED — awaiting Oscar approval to scaffold.
Date: 2026-09-10. Author: Mike (orchestrator) + Oscar.

## 1. Goal

One payment implementation for every company product. Custocare integrates
first; Custosell follows after Custocare staging proves it; app #3 gets a
one-day recipe. No rewrite per application, no logic drift, no revenue lost
to billing bugs.

## 2. Non-negotiable principles

1. **The matrix rules.** Only the transitions in §4 may happen. Anything else
   throws. No status is ever set by direct assignment outside the package.
2. **Failed never blocks.** `rejected`, `failed verification`, and `expired`
   payments are terminal records only. Re-initiation is always allowed when no
   `pending` payment exists for the same scope. Early payment (renew/top-up
   ahead of due date, Custosell parity) is a first-class path, never penalised
   (renewal extends from period end, not from now).
3. **Driver-agnostic core.** The state machine, idempotency, webhook handling,
   and approval pipeline never name a provider. Providers plug in behind
   `PaymentGatewayInterface`.
4. **Currency choice lives with the driver.** Each driver declares
   `getSupportedCurrencies()`; the core charges in the requested currency when
   supported, else USD (the Custosell East-African rule ships as the default
   strategy, overridable per product).
5. **Zero data migration.** The package never owns schema. The host payment
   model implements `GatewayPayable`; all writes go through the package
   service. Existing `payments` rows are untouched.

## 3. Package shape

New repo: `Custospark/laravel-payments`, Composer `custospark/laravel-payments`,
Laravel-only (all products are Laravel - no framework-agnostic tax).

```
src/
  Contracts/
    PaymentGatewayInterface.php   # initiate/verify/parseWebhook/verifySignature/name/isEnabled/currencies/isRedirectBased
    GatewayPayable.php            # host payment model contract (id, status accessors, relations needed)
    CurrencyStrategyInterface.php # resolve charge currency + convert quote (default: EaCurrencyStrategy)
    ApprovesBillable.php          # host callback: applyApprovedPayment(payable) / target resolution
  Gateways/
    GatewayManager.php            # registry + extend()
    PesaPalGateway.php            # ported driver (token cache, 401-retry, IPN)
  State/
    PaymentStatus.php             # PENDING|COMPLETED|FAILED|EXPIRED (+ COMPLETED->REFUNDED)
    PaymentTransition.php         # enforced transition map + guard methods
    FinalizesApproval.php         # transactional approve pipeline (trait)
  Http/
    HandlesGatewayWebhooks.php    # webhook/callback/status-verify actions (trait for host controllers)
  Support/
    BillingMonitor.php            # reusable health checks (log sweep, stuck, queue, availability)
config/payments.php               # drivers, ttl, tolerances, sweeper age
database/migrations/              # OPTIONAL expiry-index helper only (no payments table)
routes/macros.php                 # Route::gatewayWebhooks($prefix) macro
tests/                            # contract suite: transitions, idempotency, driver fakes, webhook flows
```

Out of scope for v0.1: polymorphic `gateway_payments` table (only if a product
without a payments table appears), refunds pipeline (matrix reserves it),
multi-currency settlement reports.

## 4. Canonical matrix (enforced, not documented-only)

| From | To | Trigger | Notes |
|---|---|---|---|
| (none) | `pending` | initiate (quote-checked, duplicate-checked) | one pending per scope |
| `pending` | `completed` | gateway verify success (webhook/callback/poll) OR local bypass | atomic with host side-effects |
| `pending` | `failed` | gateway verify failed / initiation error | terminal, re-initiation open |
| `pending` | `expired` | sweeper (TTL 24h, configurable) | terminal, re-initiation open |
| `completed` | `refunded` | refund pipeline (v0.2) | reserved |
| any other pair | - | `IllegalTransitionException` | fail loud, never silent |

Allowed actions per status (what the API/UI may offer):

| Status | initiate new | verify/poll | retry pay | refund | view receipt |
|---|---|---|---|---|---|
| no payment | yes | no | - | no | no |
| `pending` | NO (409/422 + reference to existing) | yes | via verify | no | no |
| `completed` | yes (next cycle / new purchase) | no-op success | - | v0.2 | yes |
| `failed` | yes | no | yes (new payment) | no | no |
| `expired` | yes | no | yes (new payment) | no | no |

## 5. Custocare integration (this plan)

Phase A - scaffold (package repo, v0.1.0 tag):
- A1. Contracts + matrix + exceptions + `EaCurrencyStrategy`.
- A2. Port `GatewayManager` + `PesaPalGateway` (with 401-retry) + `FinalizesApproval`.
- A3. Webhook trait + route macro + `BillingMonitor` support class.
- A4. Contract test-suite green in the package (ported brutal tests).

Phase B - Custocare adopts (staging first):
- B1. `composer require custospark/laravel-payments` (VCS source until Packagist).
- B2. `Payment` implements `GatewayPayable`; `GatewayService` delegates
      initiate/verify/webhook to package services (thin wrappers, then delete).
- B3. Controllers mount package webhook trait; routes via macro (same URLs).
- B4. Expiry sweeper scheduled; `billing:monitor` delegates to package checks.
- B5. Full suite green + vera; staging deploy; one real small-amount payment.

Phase C - Custosell (only after B5): same adapter pattern, prod last.

Rollback per phase: composer pin previous tag + `git checkout` app side; no
schema changes anywhere, so rollback is code-only.

## 6. What Oscar must do (action items)

1. **Create the empty repo** `Custospark/laravel-payments` (MIT, no code - Mike
   scaffolds via PR) and confirm Mike may push branches/tags to it.
2. **Package source decision:** GitHub VCS line in composer.json (needs nothing
   new - same deploy keys in use) vs private Packagist. Default: VCS.
3. **Confirm the matrix above** - especially the 24h pending TTL and that
   `rejected` needs no cool-down before re-initiation.
4. **One real-money staging test** when B5 lands (small amount, your call).
5. **hPanel staging cron** (already requested earlier - still the last mile for
   queue/scheduler/monitor ticks).

Nothing else is on you: credentials, IPN, webhook URLs and server work stay
with Mike.
