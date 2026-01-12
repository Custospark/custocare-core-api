<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FacilityWalkinCustomer extends Model
{
    use HasFactory;

    // Table name (optional if it follows Laravel convention)
    protected $table = 'facility_walkin_customers';

    // Mass assignable fields
    protected $fillable = [
        'facility_id',
        'system_user_id',
        'patient_id',
    ];

    /**
     * Relationships
     */

    // Walkin belongs to a Facility
    public function facility()
    {
        return $this->belongsTo(Facility::class);
    }

    // Walkin belongs to a System User
    public function systemUser()
    {
        return $this->belongsTo(User::class, 'system_user_id');
    }

    // Walkin belongs to a Patient
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
