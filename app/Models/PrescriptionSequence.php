<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionSequence extends Model
{
    protected $table = 'prescription_sequences';

    protected $fillable = [
        'facility_id',
        'year',
        'month',
        'last_number',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'last_number' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}