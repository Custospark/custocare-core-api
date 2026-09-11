<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreachIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_uuid',
        'facility_id',
        'reported_by_staff_id',
        'description',
        'severity',
        'status',
        'affected_subjects_estimate',
        'detected_at',
        'pdpo_notified_at',
        'subjects_notified_at',
        'contained_at',
        'closed_at',
        'remediation_notes',
        'is_drill',
    ];

    protected $casts = [
        'affected_subjects_estimate' => 'integer',
        'is_drill' => 'boolean',
        'detected_at' => 'datetime',
        'pdpo_notified_at' => 'datetime',
        'subjects_notified_at' => 'datetime',
        'contained_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function isHighRisk(): bool
    {
        return $this->severity === 'high';
    }

    public function isOverdueForSubjectNotice(): bool
    {
        return $this->isHighRisk()
            && $this->subjects_notified_at === null
            && $this->detected_at->diffInHours(now()) >= 24;
    }
}
