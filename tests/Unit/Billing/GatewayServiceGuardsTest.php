<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Repositories\Billing\Contracts\PaymentRepositoryInterface;
use App\Services\Billing\Contracts\SubscriptionBillingDocumentServiceInterface;
use App\Services\Billing\Contracts\SubscriptionPaymentQuoteServiceInterface;
use App\Services\Billing\Contracts\SubscriptionServiceInterface;
use App\Services\Billing\Gateways\Contracts\GatewayDriverInterface;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use App\Services\Billing\Gateways\GatewayManager;
use App\Services\Billing\Gateways\GatewayService;
use App\Services\Currency\Contracts\CurrencyExchangeServiceInterface;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class FakePesapalDriver implements GatewayDriverInterface
{
    public function initiate(array $payload): array
    {
        return [
            'success' => true,
            'gateway_ref' => 'CUSTO-1-x',
            'gateway_txn_id' => 'trk-test',
            'redirect_url' => 'https://pay.example/trk-test',
            'type' => 'redirect',
            'message' => 'Redirecting to PesaPal payment page.',
            'raw_response' => [],
        ];
    }

    public function verify(string $transactionId): array
    {
        return ['success' => true, 'status' => 'successful', 'gateway_txn_id' => $transactionId];
    }

    public function parseWebhookPayload(Request $request): array
    {
        return ['gateway_txn_id' => 'trk-test', 'our_reference' => 'CUSTO-1-x', 'status' => 'pending', 'amount' => 0, 'currency' => '', 'raw_payload' => []];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'pesapal';
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getSupportedCurrencies(): array
    {
        return ['UGX', 'KES', 'TZS', 'USD'];
    }

    public function isRedirectBased(): bool
    {
        return true;
    }
}

class GatewayServiceGuardsTest extends TestCase
{
    private $manager;
    private $paymentRepo;
    private $subscriptionService;
    private $quoteService;
    private $fx;
    private $billingDocuments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Mockery::mock(GatewayManager::class);
        $this->manager->shouldReceive('driver')->with('pesapal')->andReturn(new FakePesapalDriver());

        $this->paymentRepo = Mockery::mock(PaymentRepositoryInterface::class);
        $this->subscriptionService = Mockery::mock(SubscriptionServiceInterface::class);
        $this->quoteService = Mockery::mock(SubscriptionPaymentQuoteServiceInterface::class);
        $this->fx = Mockery::mock(CurrencyExchangeServiceInterface::class);
        $this->billingDocuments = Mockery::mock(SubscriptionBillingDocumentServiceInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function service(): GatewayService
    {
        return new GatewayService(
            $this->manager,
            $this->paymentRepo,
            $this->subscriptionService,
            $this->quoteService,
            $this->fx,
            $this->billingDocuments
        );
    }

    private function subscription(): Subscription
    {
        $subscription = new Subscription();
        $subscription->forceFill(['id' => 5, 'facility_id' => 1, 'plan_id' => 2]);
        $plan = new Plan();
        $plan->forceFill(['id' => 2, 'name' => 'Plan']);
        $facility = new \App\Models\Facility();
        $facility->forceFill(['id' => 1, 'facility_name' => 'Facility']);
        $subscription->setRelation('plan', $plan);
        $subscription->setRelation('facility', $facility);

        return $subscription;
    }

    /** @test */
    public function it_blocks_a_second_pending_payment_for_the_same_subscription()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->with(5)->andReturn(new Payment());
        $this->paymentRepo->shouldReceive('create')->never();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('already has a pending payment');

        $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 100.0,
            'currency' => 'USD',
            'payment_type' => 'subscription',
        ]);
    }

    /** @test */
    public function it_rejects_amounts_that_do_not_match_the_quote()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->andReturn(null);
        $this->quoteService->shouldReceive('buildQuote')->andReturn(['total_usd' => 100.0]);
        $this->paymentRepo->shouldReceive('create')->never();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('does not match the quoted total');

        $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 10.0,
            'currency' => 'USD',
            'payment_type' => 'subscription',
        ]);
    }

    /** @test */
    public function it_falls_back_to_usd_for_unsupported_currencies()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->andReturn(null);
        $this->quoteService->shouldReceive('buildQuote')->andReturn(['total_usd' => 100.0]);

        $created = new Payment();
        $created->forceFill(['id' => 11]);
        $captured = null;
        $this->paymentRepo->shouldReceive('create')->once()->with(Mockery::on(function ($data) use (&$captured) {
            $captured = $data;

            return true;
        }))->andReturn($created);
        $this->paymentRepo->shouldReceive('update')->once()->andReturn($created);

        $result = $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 100.0,
            'currency' => 'EUR',
            'payment_type' => 'subscription',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('USD', $captured['currency']);
    }

    /** @test */
    public function it_converts_the_quote_for_east_african_currencies()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->andReturn(null);
        $this->quoteService->shouldReceive('buildQuote')->andReturn(['total_usd' => 100.0]);
        $this->fx->shouldReceive('convert')->with(100.0, 'UGX', 'USD')->andReturn(370000.0);

        $created = new Payment();
        $created->forceFill(['id' => 12]);
        $captured = null;
        $this->paymentRepo->shouldReceive('create')->once()->with(Mockery::on(function ($data) use (&$captured) {
            $captured = $data;

            return true;
        }))->andReturn($created);
        $this->paymentRepo->shouldReceive('update')->once()->andReturn($created);

        $result = $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 370000.0,
            'currency' => 'UGX',
            'payment_type' => 'subscription',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('UGX', $captured['currency']);
    }

    /** @test */
    public function it_refuses_disabled_gateways_before_touching_anything()
    {
        $disabled = Mockery::mock(GatewayDriverInterface::class);
        $disabled->shouldReceive('isEnabled')->andReturn(false);
        $this->manager->shouldReceive('driver')->with('mtn_momo')->andReturn($disabled);
        $this->paymentRepo->shouldReceive('create')->never();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('not currently enabled');

        $this->service()->initiatePayment($this->subscription(), 'mtn_momo', [
            'amount' => 100.0,
            'currency' => 'USD',
            'payment_type' => 'subscription',
        ]);
    }

    /** @test */
    public function it_rejects_unknown_payment_types()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->andReturn(null);
        $this->paymentRepo->shouldReceive('create')->never();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('Unsupported payment type');

        $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 100.0,
            'currency' => 'USD',
            'payment_type' => 'mystery_type',
        ]);
    }

    /** @test */
    public function it_aborts_when_no_fx_rate_exists_instead_of_charging_blind()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->andReturn(null);
        $this->quoteService->shouldReceive('buildQuote')->andReturn(['total_usd' => 100.0]);
        $this->fx->shouldReceive('convert')->with(100.0, 'UGX', 'USD')->andReturn(null);
        $this->paymentRepo->shouldReceive('create')->never();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('No exchange rate available');

        $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 370000.0,
            'currency' => 'UGX',
            'payment_type' => 'subscription',
        ]);
    }

    /** @test */
    public function it_requires_a_target_plan_for_upgrades()
    {
        $this->paymentRepo->shouldReceive('findPendingBySubscription')->andReturn(null);
        $this->paymentRepo->shouldReceive('create')->never();

        $this->expectException(GatewayException::class);
        $this->expectExceptionMessage('target plan');

        $this->service()->initiatePayment($this->subscription(), 'pesapal', [
            'amount' => 50.0,
            'currency' => 'USD',
            'payment_type' => 'upgrade_proration',
        ]);
    }
}
