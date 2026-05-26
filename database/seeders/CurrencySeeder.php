<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar',              'symbol' => '$',    'exchange_rate' => 1],
            ['code' => 'EUR', 'name' => 'Euro',                   'symbol' => '€',    'exchange_rate' => 0.92],
            ['code' => 'GBP', 'name' => 'British Pound',          'symbol' => '£',    'exchange_rate' => 0.79],
            ['code' => 'UGX', 'name' => 'Ugandan Shilling',       'symbol' => 'USh',  'exchange_rate' => 3700],
            ['code' => 'KES', 'name' => 'Kenyan Shilling',        'symbol' => 'KSh',  'exchange_rate' => 130],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling',     'symbol' => 'TSh',  'exchange_rate' => 2500],
            ['code' => 'RWF', 'name' => 'Rwandan Franc',          'symbol' => 'FRw',  'exchange_rate' => 1300],
            ['code' => 'NGN', 'name' => 'Nigerian Naira',         'symbol' => '₦',    'exchange_rate' => 1500],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi',          'symbol' => 'GH₵',  'exchange_rate' => 12],
            ['code' => 'ZAR', 'name' => 'South African Rand',     'symbol' => 'R',    'exchange_rate' => 18],
            ['code' => 'XAF', 'name' => 'CFA Franc (BEAC)',       'symbol' => 'FCFA', 'exchange_rate' => 600],
            ['code' => 'XOF', 'name' => 'CFA Franc (BCEAO)',      'symbol' => 'CFA',  'exchange_rate' => 600],
            ['code' => 'MWK', 'name' => 'Malawian Kwacha',        'symbol' => 'MK',   'exchange_rate' => 1700],
            ['code' => 'ETB', 'name' => 'Ethiopian Birr',         'symbol' => 'Br',   'exchange_rate' => 56],
            ['code' => 'ZMW', 'name' => 'Zambian Kwacha',         'symbol' => 'ZK',   'exchange_rate' => 25],
            ['code' => 'MUR', 'name' => 'Mauritian Rupee',        'symbol' => '₨',    'exchange_rate' => 46],
            ['code' => 'MAD', 'name' => 'Moroccan Dirham',        'symbol' => 'DH',   'exchange_rate' => 10],
            ['code' => 'GNF', 'name' => 'Guinean Franc',          'symbol' => 'FG',   'exchange_rate' => 8600],
            ['code' => 'SLL', 'name' => 'Sierra Leonean Leone',   'symbol' => 'Le',   'exchange_rate' => 22],
            ['code' => 'INR', 'name' => 'Indian Rupee',           'symbol' => '₹',    'exchange_rate' => 83],
            ['code' => 'JPY', 'name' => 'Japanese Yen',           'symbol' => '¥',    'exchange_rate' => 150],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',        'symbol' => 'CA$',  'exchange_rate' => 1.36],
            ['code' => 'AUD', 'name' => 'Australian Dollar',      'symbol' => 'A$',   'exchange_rate' => 1.54],
            ['code' => 'CHF', 'name' => 'Swiss Franc',            'symbol' => 'Fr',   'exchange_rate' => 0.89],
            ['code' => 'AED', 'name' => 'UAE Dirham',             'symbol' => 'د.إ',  'exchange_rate' => 3.67],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency,
            );
        }
    }
}
