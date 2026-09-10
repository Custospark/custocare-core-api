<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Services\Billing\Gateways\Drivers\PesaPalDriver;
use App\Services\Billing\Gateways\Exceptions\GatewayException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PesaPalDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing_gateways.pesapal', [
            'enabled' => true,
            'environment' => 'sandbox',
            'base_url_sandbox' => 'https://cybqa.pesapal.com/pesapalv3',
            'base_url_production' => 'https://pay.pesapal.com/v3',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'ipn_id' => 'test-ipn-id',
            'callback_url' => 'http://localhost/api/billing/gateway/pesapal/callback',
            'token_cache_ttl' => 3300,
        ]);
        Cache::flush();
    }

    private function tokenFake(): void
    {
        Http::fake([
            'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 'tok-123'], 200),
        ]);
    }

    /** @test */
    public function it_submits_an_order_and_returns_redirect_details()
    {
        $this->tokenFake();
        Http::fake([
            'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 'tok-123'], 200),
            'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'trk-1',
                'redirect_url' => 'https://pay.pesapal.com/checkout/trk-1',
            ], 200),
        ]);

        $result = (new PesaPalDriver())->initiate([
            'payment_id' => 7,
            'amount' => 100.0,
            'currency' => 'UGX',
            'description' => 'Custocare subscription - Plan',
            'email' => 'owner@example.com',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('redirect', $result['type']);
        $this->assertSame('trk-1', $result['gateway_txn_id']);
        $this->assertStringStartsWith('CUSTO-7-', $result['gateway_ref']);
    }

    /** @test */
    public function it_throws_when_order_submission_fails()
    {
        $this->tokenFake();
        Http::fake([
            'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 'tok-123'], 200),
            'cybqa.pesapal.com/pesapalv3/api/Transactions/SubmitOrderRequest' => Http::response(['message' => 'bad'], 400),
        ]);

        $this->expectException(GatewayException::class);

        (new PesaPalDriver())->initiate([
            'payment_id' => 7,
            'amount' => 100.0,
            'currency' => 'UGX',
            'description' => 'x',
        ]);
    }

    /** @test */
    public function it_maps_transaction_status_codes()
    {
        Http::fake([
            'cybqa.pesapal.com/pesapalv3/api/Auth/RequestToken' => Http::response(['token' => 'tok-123'], 200),
            'cybqa.pesapal.com/pesapalv3/api/Transactions/GetTransactionStatus*' => Http::sequence()
                ->push(['status_code' => 1, 'amount' => 100, 'currency' => 'UGX'], 200)
                ->push(['status_code' => 2, 'payment_status_description' => 'failed'], 200)
                ->push(['status_code' => 3], 200)
                ->push(['status_code' => 0], 200),
        ]);

        $driver = new PesaPalDriver();

        $this->assertSame('successful', $driver->verify('a')['status']);
        $this->assertSame('failed', $driver->verify('b')['status']);
        $this->assertSame('failed', $driver->verify('c')['status']);
        $this->assertSame('pending', $driver->verify('d')['status']);
    }

    /** @test */
    public function it_parses_ipn_and_callback_query_params()
    {
        $request = Request::create('/x', 'GET', [
            'OrderTrackingId' => 'trk-9',
            'OrderMerchantReference' => 'CUSTOCARE-3-20240101',
            'OrderNotificationType' => 'IPNCHANGE',
        ]);

        $parsed = (new PesaPalDriver())->parseWebhookPayload($request);

        $this->assertSame('trk-9', $parsed['gateway_txn_id']);
        $this->assertSame('CUSTOCARE-3-20240101', $parsed['our_reference']);
    }

    /** @test */
    public function it_supports_east_african_currencies()
    {
        $currencies = (new PesaPalDriver())->getSupportedCurrencies();

        foreach (['UGX', 'KES', 'TZS', 'USD'] as $expected) {
            $this->assertContains($expected, $currencies);
        }
    }
}
