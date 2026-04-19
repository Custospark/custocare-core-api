<?php
// app/Models/Allergy.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allergy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'allergies';

    protected $fillable = [
        'patient_id',
        'recorded_by',
        'visit_id',
        'allergen',
        'reaction',
        'severity',
        'clinical_notes',
        'is_active',
        'diagnosed_at',
        'resolved_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'diagnosed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ========== RELATIONSHIPS ==========
    
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    // ========== SCOPES ==========
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    // ========== HELPERS ==========
    
    public function isSevere(): bool
    {
        return $this->severity === 'severe';
    }

    public function isResolved(): bool
    {
        return !is_null($this->resolved_at);
    }

    public function resolve(): bool
    {
        $this->resolved_at = now();
        $this->is_active = false;
        return $this->save();
    }
}