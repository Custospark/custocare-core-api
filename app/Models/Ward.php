<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ward extends Model
{
    protected $table = 'wards';

    protected $fillable = [
        'facility_id',
        'code',
        'name',
        'ward_type',
        'building',
        'floor',
        'status',
        'capacity_declared',
        'capacity_operational',
        'sex_restriction',
        'age_group',
        'note',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'capacity_declared' => 'integer',
        'capacity_operational' => 'integer',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facility_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function beds(): HasMany
    {
        return $this->hasMany(WardBed::class, 'ward_id');
    }
}
