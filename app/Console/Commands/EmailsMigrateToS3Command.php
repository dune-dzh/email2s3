<?php

namespace App\Console\Commands;

use App\Services\MigrationPublisherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EmailsMigrateToS3Command extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'emails:migrate-to-s3 {--loop : Continuously publish batches until interrupted}';

    /**
     * The console command description.
     */
    protected $description = 'Publish email IDs to RabbitMQ for S3 migration';

    public function __construct(
        private readonly MigrationPublisherService $publisher,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $loop = (bool) $this->option('loop');

        do {
            $count = $this->publisher->publishNextBatch();

            if ($count === 0) {
                $this->info('No more emails to publish.');

                if (! $loop) {
                    break;
                }

                sleep(5);
            } else {
                $this->info("Published {$count} email IDs to the migration queue.");
                Log::info('Emails published to migration queue', ['count' => $count]);
            }
        } while ($loop);

        return self::SUCCESS;
    }
}

