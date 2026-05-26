<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CurrencyController;
use Illuminate\Support\Facades\Route;

/*
|── Currency exchange rates ──────────────────────────────────────────────
*/

Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies.index');
Route::get('/currencies/convert', [CurrencyController::class, 'convert'])->name('currencies.convert');
