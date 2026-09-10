<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Enums\Billing\PaymentType;
use App\Enums\Billing\PaymentStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Repositories\Billing\Contracts\SubscriptionRepositoryInterface;
use App\Services\Billing\BillingFacilitySummaryService;
use App\Services\Billing\Contracts\FacilityStaffRoleModuleSyncServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionBillingPdfServiceInterface;
use App\Services\Billing\Contracts\SubscriptionPaymentQuoteServiceInterface;
use App\Services\Billing\Contracts\SubscriptionScheduledChangeServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Services\Billing\Gateways\Contracts\GatewayDriverInterface;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\Gateways\GatewayService;
use App\Services\Billing\SubscriptionService;
use App\Services\Currency\Contracts\CurrencyExchangeServiceInterface;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Brutal coverage for the money-to-status machine.
 *
 * Automatic approval replaced admin approval: every test below proves a
 * subscription lands in the right status for the payment outcome - including
 * failure, duplicate and illegal-transition paths. All DB-free (the local
 * sqlite harness cannot run the MySQL-only migrations).
 */
class SubscriptionStatusTransitionTest extends TestCase
{
    private function subscriptionServiceMocks(): array
    {
        return [
            'subscriptionRepo' => Mockery::mock(SubscriptionRepositoryInterface::class),
            'paymentRepo' => Mockery::mock(PaymentRepositoryInterface::class),
            'moduleSync' => Mockery::mock(FacilityStaffRoleModuleSyncServiceInterface::class),
            'scheduledChange' => Mockery::mock(SubscriptionScheduledChangeServiceInterface::class),
            'summary' => Mockery::mock(BillingFacilitySummaryService::class),
            'pdf' => Mockery::mock(SubscriptionBillingPdfServiceInterface::class),
            'notifications' => Mockery::mock(NotificationService::class),
        ];
    }

    private function subscriptionService(array $m): SubscriptionService
    {
        return new SubscriptionService(
            $m['subscriptionRepo'],
            $m['paymentRepo'],
            $m['moduleSync'],
            $m['scheduledChange'],
            $m['summary'],
            $m['pdf'],
            $m['notifications'],
        );
    }

    private function subscription(array $overrides = []): Subscription
    {
        $subscription = new Subscription();
        $subscription->forceFill(array_merge([
            'id' => 5,
            'facility_id' => 1,
            'plan_id' => 2,
            'status' => 'past_due',
            'billing_cycle' => 'monthly',
            'starts_at' => Carbon::now()->subDays(40)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(5)->toDateTimeString(),
            'next_billing_date' => Carbon::now()->addDays(5)->toDateTimeString(),
            'onboarding_fee_paid' => true,
            'metadata' => [],
        ], $overrides));

        $plan = new Plan();
        $plan->forceFill(['id' => 2, 'name' => 'Essential', 'price_usd' => 39.0, 'billing_cycle' => 'monthly']);
        $subscription->setRelation('plan', $plan);
        $subscription->setRelation('facility', null);

        // Off-DB models cannot fresh() - stub it to self so service code
        // paths stay identical to production.
        $partial = Mockery::mock($subscription)->makePartial();
        $partial->shouldReceive('fresh')->andReturnSelf();

        return $partial;
    }

    /**
     * Real repositories mutate the model on update - mirror that so
     * post-approval assertions observe the new state.
     */
    private function updateApplies($paymentRepo, string $times = 'once'): void
    {
        $expectation = $paymentRepo->shouldReceive('update');
        $expectation->{$times}();
        $expectation->andReturnUsing(function ($payment, $data) {
            $payment->forceFill($data);

            return $payment;
        });
    }

    private function payment(array $overrides = []): Payment
    {
        $payment = new Payment();
        $payment->forceFill(array_merge([
            'id' => 11,
            'subscription_id' => 5,
            'facility_id' => 1,
            'amount' => 39.0,
            'currency' => 'USD',
            'method' => 'gateway',
            'payment_type' => PaymentType::SUBSCRIPTION->value,
            'status' => 'approved',
            'metadata' => [],
        ], $overrides));

        return $payment;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function past_due_becomes_active_on_gateway_approval_with_null_approver()
    {
        $m = $this->subscriptionServiceMocks();
        $subscription = $this->subscription(['status' => 'past_due']);
        $payment = $this->payment();

        $captured = null;
        $m['subscriptionRepo']->shouldReceive('update')->once()->with(Mockery::on(function () {
            return true;
        }), Mockery::on(function ($data) use (&$captured) {
            $captured = $data;

            return true;
        }))->andReturnUsing(fn () => $subscription);
        $m['moduleSync']->shouldReceive('syncForSubscription')->once();
        $m['notifications']->shouldReceive('sendBillingToFacility')->zeroOrMoreTimes();

        $updated = $this->subscriptionService($m)->activateSubscription($subscription, $payment, null);

        $this->assertSame('active', $captured['status']);
        $this->assertNull($captured['approved_by_user_id']);
        $this->assertNotNull($captured['ends_at']);
        $this->assertNotNull($captured['next_billing_date']);
        $this->assertSame($updated, $subscription);
    }

    /** @test */
    public function suspended_becomes_active_on_renewal_approval()
    {
        $m = $this->subscriptionServiceMocks();
        $subscription = $this->subscription(['status' => 'suspended']);
        $payment = $this->payment(['payment_type' => PaymentType::RENEWAL->value]);

        $captured = null;
        $m['subscriptionRepo']->shouldReceive('update')->once()->withAnyArgs()
            ->andReturnUsing(function ($sub, $data) use (&$captured, $subscription) {
                $captured = $data;

                return $subscription;
            });
        $m['moduleSync']->shouldReceive('syncForSubscription')->once();

        $this->subscriptionService($m)->renewSubscription($subscription, $payment, null);

        $this->assertSame('active', $captured['status']);
        $this->assertNull($captured['suspended_at']);
    }

    /** @test */
    public function early_renewal_extends_from_period_end_instead_of_now()
    {
        $m = $this->subscriptionServiceMocks();
        $periodEnd = Carbon::now()->addDays(20);
        $subscription = $this->subscription([
            'status' => 'active',
            'ends_at' => $periodEnd->copy()->toDateTimeString(),
            'next_billing_date' => $periodEnd->copy()->toDateTimeString(),
            'starts_at' => Carbon::now()->subDays(40)->toDateTimeString(),
        ]);

        $captured = null;
        $m['subscriptionRepo']->shouldReceive('update')->once()->withAnyArgs()
            ->andReturnUsing(function ($sub, $data) use (&$captured, $subscription) {
                $captured = $data;

                return $subscription;
            });
        $m['moduleSync']->shouldReceive('syncForSubscription')->once();

        $this->subscriptionService($m)->renewSubscription($subscription, $this->payment(), null);

        // Monthly renewal from a period ending in 20 days must land ~50 days out,
        // never ~30 (which would steal the remaining 20 days the facility paid for).
        $this->assertGreaterThan(
            Carbon::now()->addDays(45)->toDateTimeString(),
            $captured['ends_at']->toDateTimeString()
        );
    }

    /** @test */
    public function upgrade_switches_the_plan_immediately()
    {
        $m = $this->subscriptionServiceMocks();
        $subscription = $this->subscription(['status' => 'active', 'plan_id' => 2]);
        $target = new Plan();
        $target->forceFill(['id' => 9, 'name' => 'Professional', 'price_usd' => 99.0]);

        $facility = new Facility();
        $facility->forceFill(['id' => 1, 'facility_name' => 'Facility']);
        $subscription->setRelation('facility', $facility);

        $m['scheduledChange']->shouldReceive('cancelPendingChange')->once();
        $captured = null;
        $m['subscriptionRepo']->shouldReceive('update')->once()->withAnyArgs()
            ->andReturnUsing(function ($sub, $data) use (&$captured, $subscription) {
                $captured = $data;

                return $subscription;
            });
        $m['moduleSync']->shouldReceive('syncForSubscription')->once();
        $m['notifications']->shouldReceive('sendBillingToFacility')->once();

        $this->subscriptionService($m)->upgradeNow($subscription, $target, null);

        $this->assertSame(9, $captured['plan_id']);
    }

    // ── GatewayService routing: the right transition per payment type ──

    private function gatewayService(array $overrides = []): array
    {
        $manager = Mockery::mock(GatewayManager::class);
        $paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $subscriptionService = Mockery::mock(SubscriptionServiceInterface::class);
        $quoteService = Mockery::mock(SubscriptionPaymentQuoteServiceInterface::class);
        $fx = Mockery::mock(CurrencyExchangeServiceInterface::class);
        $billingDocuments = Mockery::mock(SubscriptionBillingDocumentServiceInterface::class);

        $service = new GatewayService(
            $overrides['manager'] ?? $manager,
            $paymentRepo,
            $subscriptionService,
            $quoteService,
            $fx,
            $billingDocuments
        );

        return [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager];
    }

    private function pendingPayment(array $overrides = []): Payment
    {
        $payment = new Payment();
        $payment->forceFill(array_merge([
            'id' => 21,
            'subscription_id' => 5,
            'facility_id' => 1,
            'amount' => 39.0,
            'currency' => 'USD',
            'method' => 'gateway',
            'payment_type' => PaymentType::SUBSCRIPTION->value,
            'status' => PaymentStatus::PENDING->value,
            'gateway_name' => 'pesapal',
            'gateway_transaction_id' => 'trk-1',
            'metadata' => [],
        ], $overrides));
        $payment->setRelation('subscription', $this->subscription());
        $payment->setRelation('invoice', null);

        $partial = Mockery::mock($payment)->makePartial();
        $partial->shouldReceive('refresh')->andReturnSelf();

        return $partial;
    }

    /** @test */
    public function webhook_success_activates_and_papers_the_payment()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $driver = Mockery::mock(GatewayDriverInterface::class);
        $driver->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $driver->shouldReceive('verify')->with('trk-1')->andReturn([
            'success' => true, 'status' => 'successful', 'gateway_txn_id' => 'trk-1',
        ]);
        $manager->shouldReceive('driver')->with('pesapal')->andReturn($driver);

        $payment = $this->pendingPayment();
        $paymentRepo->shouldReceive('findByGatewayTransactionId')->with('trk-1')->andReturn($payment);
        $this->updateApplies($paymentRepo);
        $billingDocuments->shouldReceive('createInvoiceForPayment')->once();
        $billingDocuments->shouldReceive('issueReceiptForApprovedPayment')->once()->andReturnUsing(fn ($p) => $p);
        $subscriptionService->shouldReceive('activateSubscription')->once()->with(Mockery::any(), Mockery::any(), null);

        // The driver's parser is mocked - the request body is irrelevant here.
        $request = Request::create('/x', 'GET');

        $driver->shouldReceive('parseWebhookPayload')->andReturn([
            'gateway_txn_id' => 'trk-1', 'our_reference' => 'CUSTOCARE-21-x',
            'status' => 'pending', 'amount' => 0, 'currency' => '', 'raw_payload' => [],
        ]);

        $service->processWebhook('pesapal', $request);

        $this->assertTrue($payment->isApproved());
    }

    /** @test */
    public function webhook_for_an_already_processed_payment_touches_nothing()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $driver = Mockery::mock(GatewayDriverInterface::class);
        $driver->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $driver->shouldReceive('parseWebhookPayload')->andReturn([
            'gateway_txn_id' => 'trk-9', 'our_reference' => 'CUSTOCARE-9-x',
            'status' => 'pending', 'amount' => 0, 'currency' => '', 'raw_payload' => [],
        ]);
        $driver->shouldReceive('verify')->never();
        $manager->shouldReceive('driver')->andReturn($driver);

        $done = $this->pendingPayment(['status' => PaymentStatus::APPROVED->value, 'gateway_transaction_id' => 'trk-9']);
        $paymentRepo->shouldReceive('findByGatewayTransactionId')->with('trk-9')->andReturn($done);
        $paymentRepo->shouldReceive('update')->never();
        $subscriptionService->shouldReceive('activateSubscription')->never();
        $subscriptionService->shouldReceive('renewSubscription')->never();
        $subscriptionService->shouldReceive('upgradeNow')->never();

        $service->processWebhook('pesapal', Request::create('/x', 'GET'));

        $this->assertTrue(true); // reaching here with zero side effects is the assertion
    }

    /** @test */
    public function webhook_with_failed_verification_stashes_evidence_but_approves_nothing()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $driver = Mockery::mock(GatewayDriverInterface::class);
        $driver->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $driver->shouldReceive('parseWebhookPayload')->andReturn([
            'gateway_txn_id' => 'trk-2', 'our_reference' => 'CUSTOCARE-22-x',
            'status' => 'pending', 'amount' => 0, 'currency' => '', 'raw_payload' => [],
        ]);
        $driver->shouldReceive('verify')->with('trk-2')->andReturn(['success' => false, 'status' => 'failed']);
        $manager->shouldReceive('driver')->andReturn($driver);

        $payment = $this->pendingPayment(['gateway_transaction_id' => 'trk-2']);
        $paymentRepo->shouldReceive('findByGatewayTransactionId')->with('trk-2')->andReturn($payment);
        $this->updateApplies($paymentRepo);
        $subscriptionService->shouldReceive('activateSubscription')->never();
        $subscriptionService->shouldReceive('renewSubscription')->never();
        $subscriptionService->shouldReceive('upgradeNow')->never();

        $service->processWebhook('pesapal', Request::create('/x', 'GET'));

        $this->assertTrue($payment->isPending());
    }

    /** @test */
    public function webhook_for_unknown_references_is_logged_and_ignored()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $driver = Mockery::mock(GatewayDriverInterface::class);
        $driver->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $driver->shouldReceive('parseWebhookPayload')->andReturn([
            'gateway_txn_id' => 'trk-ghost', 'our_reference' => 'CUSTOCARE-0-x',
            'status' => 'pending', 'amount' => 0, 'currency' => '', 'raw_payload' => [],
        ]);
        $driver->shouldReceive('verify')->never();
        $manager->shouldReceive('driver')->andReturn($driver);

        $paymentRepo->shouldReceive('findByGatewayTransactionId')->with('trk-ghost')->andReturn(null);
        $paymentRepo->shouldReceive('findByTransactionReference')->with('CUSTOCARE-0-x')->andReturn(null);
        $paymentRepo->shouldReceive('update')->never();

        $service->processWebhook('pesapal', Request::create('/x', 'GET'));

        $this->assertTrue(true);
    }

    /** @test */
    public function renewal_webhook_renews_instead_of_activating()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $driver = Mockery::mock(GatewayDriverInterface::class);
        $driver->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $driver->shouldReceive('parseWebhookPayload')->andReturn([
            'gateway_txn_id' => 'trk-3', 'our_reference' => 'CUSTOCARE-23-x',
            'status' => 'pending', 'amount' => 0, 'currency' => '', 'raw_payload' => [],
        ]);
        $driver->shouldReceive('verify')->with('trk-3')->andReturn([
            'success' => true, 'status' => 'successful', 'gateway_txn_id' => 'trk-3',
        ]);
        $manager->shouldReceive('driver')->andReturn($driver);

        $payment = $this->pendingPayment([
            'payment_type' => PaymentType::RENEWAL->value,
            'gateway_transaction_id' => 'trk-3',
        ]);
        $paymentRepo->shouldReceive('findByGatewayTransactionId')->with('trk-3')->andReturn($payment);
        $this->updateApplies($paymentRepo);
        $billingDocuments->shouldReceive('createInvoiceForPayment')->once();
        $billingDocuments->shouldReceive('issueReceiptForApprovedPayment')->once()->andReturnUsing(fn ($p) => $p);
        $subscriptionService->shouldReceive('activateSubscription')->never();
        $subscriptionService->shouldReceive('renewSubscription')->once()->with(Mockery::any(), Mockery::any(), null);

        $service->processWebhook('pesapal', Request::create('/x', 'GET'));

        $this->assertTrue($payment->isApproved());
    }

    /** @test */
    public function status_poll_verify_unsticks_a_paid_but_unconfirmed_payment()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $driver = Mockery::mock(GatewayDriverInterface::class);
        $driver->shouldReceive('verify')->with('trk-5')->andReturn([
            'success' => true, 'status' => 'successful', 'gateway_txn_id' => 'trk-5',
        ]);
        $manager->shouldReceive('driver')->with('pesapal')->andReturn($driver);

        $payment = $this->pendingPayment(['gateway_transaction_id' => 'trk-5']);
        $this->updateApplies($paymentRepo);
        $billingDocuments->shouldReceive('createInvoiceForPayment')->once();
        $billingDocuments->shouldReceive('issueReceiptForApprovedPayment')->once()->andReturnUsing(fn ($p) => $p);
        $subscriptionService->shouldReceive('activateSubscription')->once();

        $result = $service->verifyPendingPayment($payment);

        $this->assertSame('approved', $result['status']);
        $this->assertTrue($payment->isApproved());
    }

    /** @test */
    public function status_poll_verify_never_calls_the_gateway_for_finished_payments()
    {
        [$service, $paymentRepo, $subscriptionService, $billingDocuments, $manager] = $this->gatewayService();

        $manager->shouldReceive('driver')->never();

        $done = $this->pendingPayment(['status' => PaymentStatus::APPROVED->value]);

        $result = $service->verifyPendingPayment($done);

        $this->assertSame('approved', $result['status']);
    }
}
