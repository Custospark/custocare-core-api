<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Plan
 *
 * Represents a Custocare subscription tier available to facilities.
 * Plans are global — NOT tied to any app or user.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $description
 * @property float       $price_usd
 * @property float       $price_ugx
 * @property float       $onboarding_fee_usd
 * @property float       $onboarding_fee_ugx
 * @property string      $billing_cycle
 * @property int         $trial_days
 * @property array|null  $features
 * @property int|null    $max_staff
 * @property int|null    $max_departments
 * @property int|null    $max_visits_per_month
 * @property int         $sort_order
 * @property bool        $is_popular
 * @property bool        $is_active
 * @property array|null  $metadata
 */
class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_usd',
        'price_ugx',
        'onboarding_fee_usd',
        'onboarding_fee_ugx',
        'billing_cycle',
        'trial_days',
        'features',
        'max_staff',
        'max_departments',
        'max_visits_per_month',
        'sort_order',
        'is_popular',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'price_usd'              => 'float',
        'price_ugx'              => 'float',
        'onboarding_fee_usd'     => 'float',
        'onboarding_fee_ugx'     => 'float',
        'trial_days'             => 'integer',
        'max_staff'              => 'integer',
        'max_departments'        => 'integer',
        'max_visits_per_month' => 'integer',
        'sort_order'             => 'integer',
        'is_popular'             => 'boolean',
        'is_active'              => 'boolean',
        'features'               => 'array',
        'metadata'               => 'array',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_usd');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /** Price in a given currency; defaults to UGX. */
    public function priceIn(string $currency = 'UGX'): float
    {
        return strtoupper($currency) === 'USD' ? $this->price_usd : $this->price_ugx;
    }

    /** Onboarding fee in a given currency. */
    public function onboardingFeeIn(string $currency = 'UGX'): float
    {
        return strtoupper($currency) === 'USD'
            ? $this->onboarding_fee_usd
            : $this->onboarding_fee_ugx;
    }

    /** Whether this plan carries a one-time onboarding fee. */
    public function hasOnboardingFee(): bool
    {
        return $this->onboarding_fee_ugx > 0 || $this->onboarding_fee_usd > 0;
    }
}
