<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpaceOccupancyChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $facilityId,
        public int $spaceId,
        public string $action
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('facility.' . $this->facilityId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SpaceOccupancyChanged';
    }
}
