<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Billing\AssignableModuleController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Billing\PaymentController;
use App\Http\Controllers\Api\Billing\PlanController;
use App\Http\Controllers\Api\Billing\SubscriptionController;
use App\Http\Controllers\Api\Billing\UsageController;
use App\Http\Controllers\Api\Billing\Admin;
use Illuminate\Support\Facades\Route;

/*
|──────────────────────────────────────────────────────────────────────────────
| CUSTOCARE BILLING ROUTES
|
| Architecture:
|   PUBLIC        → Plan listing (no auth)
|   FACILITY      → Subscription & payment submission (auth:sanctum)
|   ADMIN         → Approve/reject payments, manage subscriptions (auth + admin.access)
|
| Middleware aliases (registered in bootstrap/app.php):
|   'facility.subscription.active' → EnsureFacilitySubscriptionIsActive
|   'admin.access'                 → EnsureAdminAccess
|──────────────────────────────────────────────────────────────────────────────
*/

/*
|──────────────────────────────────────────────────────────────────────────────
| [1] PUBLIC — Facilities browse plans before subscribing (no auth required)
|──────────────────────────────────────────────────────────────────────────────
*/
Route::prefix('billing')
    ->name('billing.')
    ->group(function () {

        Route::get('/plans',        [PlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->name('plans.show');
    });

/*
|──────────────────────────────────────────────────────────────────────────────
| [2] FACILITY-FACING(Faciity Administrator) — Requires auth; scoped per facility route parameter.
|
| Every route here is under /api/facilities/{facility}/...
| The {facility} is resolved via Laravel route model binding → Facility model.
|
| NOTE: The 'facility.subscription.active' middleware is intentionally NOT
|       applied to subscription/payment submission routes — a facility must
|       be able to create a subscription and submit payments even when
|       their subscription is inactive.
|──────────────────────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum'])
    ->prefix('facilities/{facility}')
    ->name('facilities.')
    ->group(function () {

        /*
        |── Subscription ────────────────────────────────────────────────────
        | GET    /facilities/{facility}/subscription         → show current subscription
        | POST   /facilities/{facility}/subscription         → create new subscription
        | DELETE /facilities/{facility}/subscription         → cancel subscription
        */
        Route::prefix('subscription')
            ->name('subscription.')
            ->group(function () {
                Route::get('/',    [SubscriptionController::class, 'show'])  ->name('show');
                Route::post('/',   [SubscriptionController::class, 'store']) ->name('store');
                Route::delete('/', [SubscriptionController::class, 'cancel'])->name('cancel');
            });

        /*
        |── Payments ────────────────────────────────────────────────────────
        | GET    /facilities/{facility}/payments             → list facility payments
        | POST   /facilities/{facility}/payments             → record new manual payment
        | GET    /facilities/{facility}/payments/{payment}   → show a specific payment
        */
        Route::prefix('payments')
            ->name('payments.')
            ->group(function () {
                Route::get('/',          [PaymentController::class, 'index'])->name('index');
                Route::post('/',         [PaymentController::class, 'store'])->name('store');
                Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
                Route::get('/{payment}/receipt', [PaymentController::class, 'receipt'])->name('receipt');
            });

        /*
        |── Invoices ────────────────────────────────────────────────────────
        | GET    /facilities/{facility}/invoices             → list invoices
        | GET    /facilities/{facility}/invoices/{invoice}   → show invoice
        */
        Route::prefix('invoices')
            ->name('invoices.')
            ->group(function () {
                Route::get('/',          [InvoiceController::class, 'index'])->name('index');
                Route::get('/{invoice}', [InvoiceController::class, 'show']) ->name('show');
            });

        /*
        |── Usage ───────────────────────────────────────────────────────────
        | GET    /facilities/{facility}/usage               → current usage counts
        */
        Route::get('/usage', [UsageController::class, 'index'])->name('usage');

        Route::get('/assignable-modules', [AssignableModuleController::class, 'index'])
            ->name('assignable-modules');
    });

/*
|──────────────────────────────────────────────────────────────────────────────
| [3] ADMIN — Requires auth:sanctum + admin.access (Platform Admin:super_admin role)
|
| These are the CORE MANUAL BILLING ENDPOINTS.
| Admins view pending payments, approve or reject them, and manage subscriptions.
|
| POST /approve → confirms receipt evidence → activates subscription
| POST /reject  → rejects with mandatory reason
|──────────────────────────────────────────────────────────────────────────────
*/
Route::middleware(['auth:sanctum',])
    ->prefix('admin/billing')
    ->name('admin.billing.')
    ->group(function () {

        /*
        |── Plan Management (Admin Full CRUD) ──────────────────────────────
        | GET    /admin/billing/plans             → list all plans
        | POST   /admin/billing/plans             → create new plan
        | GET    /admin/billing/plans/{plan}      → show plan
        | PUT    /admin/billing/plans/{plan}      → update plan
        | DELETE /admin/billing/plans/{plan}      → delete plan
        */
        Route::apiResource('plans', Admin\PlanController::class)
            ->names([
                'index'   => 'plans.index',
                'store'   => 'plans.store',
                'show'    => 'plans.show',
                'update'  => 'plans.update',
                'destroy' => 'plans.destroy',
            ]);

        /*
        |── Subscription Management (Admin) ────────────────────────────────
        | GET    /admin/billing/subscriptions                    → list all
        | GET    /admin/billing/subscriptions/{sub}              → show detail
        | POST   /admin/billing/subscriptions/{sub}/activate     → manual activate
        | POST   /admin/billing/subscriptions/{sub}/suspend      → manual suspend
        | POST   /admin/billing/subscriptions/{sub}/cancel       → admin cancel
        */
        Route::prefix('subscriptions')
            ->name('subscriptions.')
            ->group(function () {

                Route::get('/', [Admin\SubscriptionController::class, 'index'])
                    ->name('index');

                Route::get('/{subscription}', [Admin\SubscriptionController::class, 'show'])
                    ->name('show');

                Route::post('/{subscription}/activate', [Admin\SubscriptionController::class, 'activate'])
                    ->name('activate');

                Route::post('/{subscription}/suspend', [Admin\SubscriptionController::class, 'suspend'])
                    ->name('suspend');

                Route::post('/{subscription}/cancel', [Admin\SubscriptionController::class, 'cancel'])
                    ->name('cancel');
            });

        /*
        |── Payment Management (Platform Admin) ──────────────────────────────────────
        |
        | ✅ APPROVE → POST /admin/billing/payments/{payment}/approve
        |    - Confirms receipt/evidence of payment
        |    - Triggers subscription activation (new) or renewal (existing)
        |    - This is the PRIMARY manual billing approval endpoint
        |
        | ❌ REJECT  → POST /admin/billing/payments/{payment}/reject
        |    - Rejects payment with a mandatory reason
        |    - Subscription remains in current status
        |
        | GET /admin/billing/payments?status=pending  → admin payment queue
        */
        Route::prefix('payments')
            ->name('payments.')
            ->group(function () {

                Route::get('/', [Admin\PaymentController::class, 'index'])
                    ->name('index');

                Route::get('/{payment}', [Admin\PaymentController::class, 'show'])
                    ->name('show');

                Route::get('/{payment}/receipt', [Admin\PaymentController::class, 'receipt'])
                    ->name('receipt');

                // ── Core manual billing approval endpoints ────────────────
                Route::post('/{payment}/approve', [Admin\PaymentController::class, 'approve'])
                    ->name('approve');

                Route::post('/{payment}/reject', [Admin\PaymentController::class, 'reject'])
                    ->name('reject');
            });

        /*
        |── Invoice Management (Admin) ─────────────────────────────────────
        | GET    /admin/billing/invoices                    → list all invoices
        | GET    /admin/billing/invoices/{invoice}          → show invoice
        | POST   /admin/billing/invoices/{invoice}/mark-paid→ mark as paid
        | POST   /admin/billing/invoices/{invoice}/cancel   → cancel invoice
        */
        Route::prefix('invoices')
            ->name('invoices.')
            ->group(function () {
                Route::get('/',                   [Admin\InvoiceController::class, 'index'])->name('index');
                Route::get('/{invoice}',           [Admin\InvoiceController::class, 'show'])->name('show');
                Route::post('/{invoice}/mark-paid',[Admin\InvoiceController::class, 'markPaid'])->name('mark-paid');
                Route::post('/{invoice}/cancel',   [Admin\InvoiceController::class, 'cancel'])->name('cancel');
            });
    });
