<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_uuid',
        'title',
        'environment',
        'commit_range',
        'requested_by',
        'approved_by',
        'approved_at',
        'executed_at',
        'rollback_ref',
        'test_evidence',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function isApproved(): bool
    {
        return $this->approved_by !== null && $this->approved_at !== null;
    }

    public function isExecuted(): bool
    {
        return $this->executed_at !== null;
    }

    /**
     * A change may only execute with approval recorded (the signed form).
     */
    public function canExecute(): bool
    {
        return $this->isApproved() && ! $this->isExecuted();
    }
}
