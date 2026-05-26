<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Currency;
use App\Services\Currency\Contracts\CurrencyExchangeServiceInterface;

class CurrencyHelper
{
    public static function convert(float $amount, string $to, string $from = 'USD'): ?float
    {
        return app(CurrencyExchangeServiceInterface::class)->convert($amount, $to, $from);
    }

    public static function format(float $amount, string $currencyCode = 'USD'): string
    {
        $symbol = self::getSymbol($currencyCode);

        return "{$symbol}" . number_format($amount, 2);
    }

    public static function getSymbol(string $currencyCode): string
    {
        return Currency::where('code', strtoupper($currencyCode))->value('symbol') ?? $currencyCode;
    }

    public static function getActiveCurrencies()
    {
        return Currency::where('is_active', true)->orderBy('name')->get();
    }

    public static function formatWithConverted(float $usdAmount, string $targetCurrency): array
    {
        $converted = self::convert($usdAmount, $targetCurrency);

        return [
            'usd'        => self::format($usdAmount, 'USD'),
            'converted'  => $converted !== null ? self::format($converted, $targetCurrency) : null,
            'currency'   => $targetCurrency,
            'rate'       => $converted !== null ? round($converted / $usdAmount, 6) : null,
        ];
    }
}
