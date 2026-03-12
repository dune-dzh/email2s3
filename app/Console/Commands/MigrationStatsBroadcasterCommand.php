<?php

namespace App\Console\Commands;

use App\Services\MigrationStatsService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MigrationStatsBroadcasterCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migration:stats-broadcaster {--interval=1 : Interval in seconds between broadcasts}';

    /**
     * The console command description.
     */
    protected $description = 'Broadcast migration statistics at a fixed interval (logs/console)';

    private bool $broadcastSkippedLogged = false;

    public function __construct(
        private readonly MigrationStatsService $statsService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $interval = (int) $this->option('interval');
        if ($interval <= 0) {
            $interval = 1;
        }

        $this->info("Starting migration stats broadcaster with {$interval}s interval");

        while (true) {
            $stats = $this->statsService->gather();

            Log::info('Migration stats', $stats);
            $this->line(json_encode($stats));

            $broadcaster = null;
            try {
                $broadcaster = app('broadcaster')->connection('reverb');
            } catch (\Throwable) {
                // Connection not available (e.g. Reverb driver not registered)
            }

            $usePusher = $broadcaster === null
                || $broadcaster instanceof PusherBroadcaster
                || (is_object($broadcaster) && str_contains(get_class($broadcaster), 'PusherBroadcaster'));

            if ($usePusher) {
                if (!$this->broadcastSkippedLogged) {
                    Log::warning('Migration stats broadcast skipped: not using Reverb. Set BROADCAST_DRIVER=reverb, ensure Reverb is configured, then run: php artisan config:clear');
                    $this->broadcastSkippedLogged = true;
                }
            } else {
                try {
                    Config::set('broadcasting.default', 'reverb');
                    event(new \App\Events\MigrationStatsUpdated(
                        $stats['total'],
                        $stats['pending'],
                        $stats['migrating'],
                        $stats['migrated'],
                        $stats['timestamp'] ?? null,
                    ));
                } catch (BroadcastException $e) {
                    Log::warning('Migration stats broadcast failed (Reverb may be down or misconfigured)', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }
}

