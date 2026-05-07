<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NursingMedicationAdministration extends Model
{
    protected $table = 'nursing_medication_administrations';

    protected $fillable = [
        'nursing_medication_dose_id',
        'facility_id',
        'visit_id',
        'prescription_item_id',
        'administered_by_user_id',
        'administered_at',
        'outcome',
        'quantity_given',
        'quantity_unit',
        'notes',
        'refusal_or_omission_reason',
    ];

    protected function casts(): array
    {
        return [
            'administered_at' => 'datetime',
            'quantity_given' => 'decimal:3',
        ];
    }

    public function dose(): BelongsTo
    {
        return $this->belongsTo(NursingMedicationDose::class, 'nursing_medication_dose_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by_user_id');
    }
}
