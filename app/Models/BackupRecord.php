<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'backup_uuid',
        'environment',
        'path',
        'bytes',
        'kind',
        'taken_at',
        'restore_tested_at',
        'restore_ok',
        'notes',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'restore_ok' => 'boolean',
        'taken_at' => 'datetime',
        'restore_tested_at' => 'datetime',
    ];

    public function isUsable(): bool
    {
        return $this->bytes > 0;
    }
}
