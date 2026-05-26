<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\Currency\Contracts\CurrencyExchangeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(
        private readonly CurrencyExchangeServiceInterface $exchangeService,
    ) {}

    /**
     * GET /api/currencies
     *
     * List all active currencies with their symbols.
     */
    public function index(): JsonResponse
    {
        $currencies = Currency::active()->orderBy('name')->get(['code', 'name', 'symbol']);

        return response()->json([
            'success' => true,
            'data'    => $currencies,
        ]);
    }

    /**
     * GET /api/currencies/convert
     *
     * Convert an amount from one currency to another.
     *
     * @queryParam amount float required The amount to convert.
     * @queryParam from string required Source currency code (default: USD).
     * @queryParam to string required Target currency code.
     */
    public function convert(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'from'   => 'required|string|size:3',
            'to'     => 'required|string|size:3',
        ]);

        $amount = (float) $request->amount;
        $from   = strtoupper($request->from);
        $to     = strtoupper($request->to);

        $converted = $this->exchangeService->convert($amount, $to, $from);
        $rate      = $this->exchangeService->getExchangeRate($from, $to);

        return response()->json([
            'success' => true,
            'data'    => [
                'amount'     => $amount,
                'from'       => $from,
                'to'         => $to,
                'converted'  => $converted,
                'rate'       => $rate,
                'formatted'  => $converted !== null
                    ? Currency::where('code', $to)->value('symbol') . number_format($converted, 2)
                    : null,
            ],
        ]);
    }
}
