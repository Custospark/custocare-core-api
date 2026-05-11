<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class HubSupportTicketUpdate extends Model
{
    protected $fillable = [
        'uuid',
        'hub_support_ticket_id',
        'status',
        'note',
    ];

    protected static function booted(): void
    {
        static::creating(function (HubSupportTicketUpdate $row): void {
            if (empty($row->uuid)) {
                $row->uuid = (string) Str::uuid();
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HubSupportTicket::class, 'hub_support_ticket_id');
    }
}

