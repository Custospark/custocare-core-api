<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Billing\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Repositories\Billing\Contracts\PlanRepositoryInterface;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Repositories\Billing\Contracts\SubscriptionScheduledChangeRepositoryInterface;
use App\Repositories\Billing\InvoiceRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Billing\PlanRepository;
use App\Repositories\Billing\SubscriptionRepository;
use App\Repositories\Billing\SubscriptionScheduledChangeRepository;
use App\Services\Billing\Contracts\InvoiceServiceInterface;
use App\Services\Billing\Contracts\PaymentServiceInterface;
use App\Services\Billing\AssignableModuleService;
use App\Services\Billing\Contracts\AssignableModuleServiceInterface;
use App\Services\Billing\Contracts\FacilityStaffRoleModuleSyncServiceInterface;
use App\Services\Currency\Contracts\CurrencyExchangeServiceInterface;
use App\Services\Currency\CurrencyExchangeService;
use App\Services\Billing\Contracts\PlanLimitServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingPdfServiceInterface;
use App\Services\Billing\Contracts\SubscriptionPaymentQuoteServiceInterface;
use App\Services\Billing\Contracts\SubscriptionScheduledChangeServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Services\Billing\Contracts\UsageServiceInterface;
use App\Services\Billing\SubscriptionBillingDocumentService;
use App\Services\Billing\SubscriptionBillingPdfService;
use App\Services\Billing\SubscriptionPaymentQuoteService;
use App\Services\Billing\SubscriptionScheduledChangeService;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\Gateways\GatewayService;
use App\Services\Billing\FacilityStaffRoleModuleSyncService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PlanLimitService;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\UsageService;
use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Repositories ──────────────────────────────────────────────────
        $this->app->bind(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);
        $this->app->bind(SubscriptionScheduledChangeRepositoryInterface::class, SubscriptionScheduledChangeRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);

        // ── Services ──────────────────────────────────────────────────────
        $this->app->bind(SubscriptionScheduledChangeServiceInterface::class, SubscriptionScheduledChangeService::class);
        $this->app->bind(SubscriptionPaymentQuoteServiceInterface::class, SubscriptionPaymentQuoteService::class);
        $this->app->bind(
            SubscriptionBillingDocumentServiceInterface::class,
            SubscriptionBillingDocumentService::class,
        );
        $this->app->bind(
            SubscriptionBillingPdfServiceInterface::class,
            SubscriptionBillingPdfService::class,
        );
        $this->app->bind(SubscriptionServiceInterface::class, SubscriptionService::class);
        $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
        $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
        $this->app->bind(UsageServiceInterface::class, UsageService::class);
        $this->app->bind(PlanLimitServiceInterface::class, PlanLimitService::class);
        $this->app->bind(
            FacilityStaffRoleModuleSyncServiceInterface::class,
            FacilityStaffRoleModuleSyncService::class,
        );
        $this->app->bind(
            AssignableModuleServiceInterface::class,
            AssignableModuleService::class,
        );

        // ── Currency exchange ──────────────────────────────────────────
        $this->app->bind(
            CurrencyExchangeServiceInterface::class,
            CurrencyExchangeService::class,
        );

        // ── Gateway infrastructure ────────────────────────────────────────
        // GatewayManager is a singleton — one instance per request lifecycle,
        // caches resolved driver instances.
        $this->app->singleton(GatewayManager::class);

        // GatewayService is resolved fresh each time (uses singleton manager internally)
        $this->app->bind(GatewayService::class);
    }

    public function boot(): void {}
}
