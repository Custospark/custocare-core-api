<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Facility;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FacilityStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Facility $facility,
        public readonly string $oldStatus,
        public readonly string $newStatus,
        public readonly ?string $reason,
        public readonly int $changedByStaffId
    ) {}
}