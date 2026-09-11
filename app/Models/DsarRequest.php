<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class DsarRequest extends Model
{
    use HasFactory;

    /** SLA: 5 working days from receipt (Guidelines Sec 7/9). */
    public const SLA_WORKING_DAYS = 5;

    protected $fillable = [
        'request_uuid',
        'patient_id',
        'requested_by_staff_id',
        'request_type',
        'channel',
        'details',
        'status',
        'sla_due_at',
        'resolution_notes',
        'rejection_reasons',
        'downstream_notified',
        'fulfilled_at',
    ];

    protected $casts = [
        'downstream_notified' => 'boolean',
        'sla_due_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public static function slaDueAt(Carbon|string|null $receivedAt = null): Carbon
    {
        return Carbon::parse($receivedAt ?? now())->addWeekdays(self::SLA_WORKING_DAYS);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['received', 'in_review'], true)
            && $this->sla_due_at !== null
            && $this->sla_due_at->isPast();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['fulfilled', 'rejected'], true);
    }
}
