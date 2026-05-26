<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\InvoiceStatus;
use App\Enums\Billing\InvoiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int            $id
 * @property int            $subscription_id
 * @property int            $facility_id
 * @property string         $invoice_number
 * @property InvoiceType    $invoice_type
 * @property InvoiceStatus  $status
 * @property float          $amount
 * @property string         $currency
 * @property float          $paid_amount
 * @property string|null    $description
 * @property array|null     $line_items
 * @property Carbon         $issued_at
 * @property Carbon         $due_at
 * @property Carbon|null    $paid_at
 * @property Carbon|null    $cancelled_at
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'facility_id',
        'payment_id',
        'invoice_number',
        'invoice_type',
        'status',
        'amount',
        'currency',
        'paid_amount',
        'description',
        'line_items',
        'issued_at',
        'due_at',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'invoice_type' => InvoiceType::class,
        'status'       => InvoiceStatus::class,
        'amount'       => 'float',
        'paid_amount'  => 'float',
        'line_items'   => 'array',
        'issued_at'    => 'date',
        'due_at'       => 'date',
        'paid_at'      => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeForFacility($query, int $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', InvoiceStatus::PAID);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE]);
    }

    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::OVERDUE;
    }

    public function balanceDue(): float
    {
        return max(0, $this->amount - $this->paid_amount);
    }
}
