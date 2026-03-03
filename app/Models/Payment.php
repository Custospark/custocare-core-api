<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\PaymentMethod;
use App\Enums\Billing\PaymentStatus;
use App\Enums\Billing\PaymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
/**
 * Class Payment
 *
 * Records a payment attempt for a facility subscription.
 * In the manual billing phase, payments are created by facilities
 * (with optional receipt upload) and approved/rejected by admins.
 *
 * @property int            $id
 * @property int            $subscription_id
 * @property int            $facility_id
 * @property float          $amount
 * @property string         $currency
 * @property PaymentMethod  $method
 * @property PaymentType    $payment_type
 * @property PaymentStatus  $status
 * @property string|null    $transaction_reference
 * @property string|null    $receipt_path
 * @property string|null    $receipt_notes
 * @property Carbon|null    $paid_at
 * @property Carbon|null    $approved_at
 * @property int|null       $approved_by_staff_id
 * @property string|null    $rejection_reason
 * @property string|null    $gateway_name
 * @property string|null    $gateway_transaction_id
 * @property array|null     $gateway_response
 * @property array|null     $metadata
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'facility_id',
        'amount',
        'currency',
        'method',
        'payment_type',
        'status',
        'transaction_reference',
        'receipt_path',
        'receipt_notes',
        'paid_at',
        'approved_at',
        'approved_by_staff_id',
        'rejection_reason',
        'gateway_name',
        'gateway_transaction_id',
        'gateway_response',
        'metadata',
    ];

    protected $casts = [
        'amount'           => 'float',
        'method'           => PaymentMethod::class,
        'payment_type'     => PaymentType::class,
        'status'           => PaymentStatus::class,
        'paid_at'          => 'datetime',
        'approved_at'      => 'datetime',
        'gateway_response' => 'array',
        'metadata'         => 'array',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by_staff_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', PaymentStatus::APPROVED);
    }

    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === PaymentStatus::APPROVED;
    }

    public function receiptUrl(): ?string
    {
        if (! $this->receipt_path) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->receipt_path);
}
}
