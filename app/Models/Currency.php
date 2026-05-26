<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'exchange_rate',
        'symbol',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'float',
            'is_active'     => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
