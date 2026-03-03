<?php

declare(strict_types=1);

/**
 * CUSTOCARE — PAYMENT GATEWAY CONFIGURATION
 *
 * All gateway drivers are disabled by default.
 * Enable individual gateways via .env when credentials are obtained.
 *
 * Manual billing (method=mobile_money|bank_transfer|cash) remains fully
 * operational regardless of which gateways are enabled here.
 */
return [

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Default gateway used when none is specified.
    | 'manual' means no gateway is used — admin approval required.
    |──────────────────────────────────────────────────────────────────────────
    */
    'default' => env('BILLING_GATEWAY', 'manual'),

    /*
    |──────────────────────────────────────────────────────────────────────────
    | MTN Mobile Money Uganda — Collections API
    |
    | Docs: https://momodeveloper.mtn.com/
    | Flow: Push USSD → Customer approves on phone → Webhook confirms
    | Phone format: 256770123456 (Uganda, no + prefix)
    |──────────────────────────────────────────────────────────────────────────
    */
    'mtn_momo' => [
        'enabled'              => env('MTN_MOMO_ENABLED', false),
        'environment'          => env('MTN_MOMO_ENVIRONMENT', 'sandbox'), // sandbox | production
        'base_url_sandbox'     => 'https://sandbox.momodeveloper.mtn.com',
        'base_url_production'  => 'https://proxy.momoapi.mtn.com',
        'subscription_key'     => env('MTN_MOMO_SUBSCRIPTION_KEY'),       // Ocp-Apim-Subscription-Key
        'api_user'             => env('MTN_MOMO_API_USER'),               // UUID of the API user
        'api_key'              => env('MTN_MOMO_API_KEY'),                // API key for the user
        'currency'             => env('MTN_MOMO_CURRENCY', 'UGX'),        // EUR in sandbox, UGX in prod
        'callback_url'         => env('MTN_MOMO_CALLBACK_URL'),           // Our webhook endpoint
        'token_cache_ttl'      => 3500,                                    // seconds (token expires in 3600)
    ],

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Airtel Money Uganda — Airtel Africa API
    |
    | Docs: https://developers.airtel.africa/
    | Flow: Push USSD → Customer approves on phone → Webhook confirms
    | Phone format: 256751234567 (Uganda, no + prefix)
    |──────────────────────────────────────────────────────────────────────────
    */
    'airtel_money' => [
        'enabled'         => env('AIRTEL_MONEY_ENABLED', false),
        'environment'     => env('AIRTEL_MONEY_ENVIRONMENT', 'sandbox'),
        'base_url'        => env('AIRTEL_MONEY_BASE_URL', 'https://openapi.airtel.africa'),
        'client_id'       => env('AIRTEL_CLIENT_ID'),
        'client_secret'   => env('AIRTEL_CLIENT_SECRET'),
        'country'         => 'UG',
        'currency'        => 'UGX',
        'callback_url'    => env('AIRTEL_CALLBACK_URL'),
        'token_cache_ttl' => 3500,
    ],

    /*
    |──────────────────────────────────────────────────────────────────────────
    | Flutterwave — v3 Payments API
    |
    | Docs: https://developer.flutterwave.com/
    | Flow: Initialize → Redirect to hosted page → Callback → Verify
    | Also supports: Webhook for async confirmation
    | Supports: Cards, Mobile Money UG, Bank Transfer
    |──────────────────────────────────────────────────────────────────────────
    */
    'flutterwave' => [
        'enabled'         => env('FLUTTERWAVE_ENABLED', false),
        'secret_key'      => env('FLW_SECRET_KEY'),
        'public_key'      => env('FLW_PUBLIC_KEY'),
        'encryption_key'  => env('FLW_ENCRYPTION_KEY'),
        'base_url'        => 'https://api.flutterwave.com/v3',
        'redirect_url'    => env('FLW_REDIRECT_URL'),        // Where user lands after payment
        'webhook_secret'  => env('FLW_WEBHOOK_SECRET'),      // Used to verify webhook signature
    ],

    /*
    |──────────────────────────────────────────────────────────────────────────
    | PesaPal — v3 API
    |
    | Docs: https://developer.pesapal.com/
    | Flow: Get token → Submit order → Redirect → IPN callback → Verify
    | Supports: Mobile Money, Cards, Bank
    |──────────────────────────────────────────────────────────────────────────
    */
    'pesapal' => [
        'enabled'              => env('PESAPAL_ENABLED', false),
        'environment'          => env('PESAPAL_ENVIRONMENT', 'sandbox'),
        'base_url_sandbox'     => 'https://cybqa.pesapal.com/pesapalv3',
        'base_url_production'  => 'https://pay.pesapal.com/v3',
        'consumer_key'         => env('PESAPAL_CONSUMER_KEY'),
        'consumer_secret'      => env('PESAPAL_CONSUMER_SECRET'),
        'ipn_id'               => env('PESAPAL_IPN_ID'),          // Registered IPN ID
        'callback_url'         => env('PESAPAL_CALLBACK_URL'),
        'token_cache_ttl'      => 3300,                            // seconds (5min buffer before 60min expiry)
    ],

];
