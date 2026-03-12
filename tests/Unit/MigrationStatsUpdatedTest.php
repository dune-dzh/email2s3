<?php

namespace Tests\Unit;

use App\Events\MigrationStatsUpdated;
use Illuminate\Broadcasting\Channel;
use PHPUnit\Framework\TestCase;

class MigrationStatsUpdatedTest extends TestCase
{
    public function test_event_broadcasts_on_migration_stats_channel(): void
    {
        $event = new MigrationStatsUpdated(100, 50, 2, 48, '2026-03-10T12:00:00Z');

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('migration-stats', $channels[0]->name);
    }

    public function test_event_broadcast_name_and_payload(): void
    {
        $event = new MigrationStatsUpdated(100, 50, 2, 48, '2026-03-10T12:00:00Z');

        $this->assertSame('MigrationStatsUpdated', $event->broadcastAs());

        $payload = $event->broadcastWith();
        $this->assertSame(100, $payload['total']);
        $this->assertSame(50, $payload['pending']);
        $this->assertSame(2, $payload['migrating']);
        $this->assertSame(48, $payload['migrated']);
        $this->assertSame('2026-03-10T12:00:00Z', $payload['timestamp']);
    }
}
