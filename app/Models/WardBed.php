<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WardBed extends Model
{
    use SoftDeletes;

    protected $table = 'ward_beds';

    protected $fillable = [
        'facility_id',
        'ward_id',
        'bed_label',
        'status',
        'note',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }
}

