<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FacilityRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_system_role',
        'facility_id'
    ];

    protected $casts = [
        'is_system_role' => 'boolean',
    ];

    /**
     * Scopes
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system_role', true);
    }

  
}
