<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MigrationStatsUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $total,
        public readonly int $pending,
        public readonly int $migrating,
        public readonly int $migrated,
        public readonly ?string $timestamp,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('migration-stats'),
        ];
    }

    /**
     * Use Reverb instead of Pusher (avoids "No matching application" when default is wrong or cached).
     */
    public function broadcastConnection(): string
    {
        return 'reverb';
    }

    public function broadcastAs(): string
    {
        return 'MigrationStatsUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'total' => $this->total,
            'pending' => $this->pending,
            'migrating' => $this->migrating,
            'migrated' => $this->migrated,
            'timestamp' => $this->timestamp,
        ];
    }
}
