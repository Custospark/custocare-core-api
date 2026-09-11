<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataTransferRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_uuid',
        'destination_country',
        'recipient',
        'data_categories',
        'legal_basis',
        'adequacy_notes',
        'safeguards',
        'justification',
        'pdpo_authorisation_ref',
        'moh_consent',
        'status',
    ];

    protected $casts = [
        'moh_consent' => 'boolean',
    ];

    /**
     * A transfer may proceed only when authorized with a complete basis:
     * adequacy or consent PLUS pdpo reference PLUS MoH consent.
     */
    public function isAuthorized(): bool
    {
        return $this->status === 'authorized'
            && in_array($this->legal_basis, ['adequacy', 'consent'], true)
            && ! empty($this->pdpo_authorisation_ref)
            && $this->moh_consent === true;
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked' || $this->legal_basis === 'prohibited';
    }
}
