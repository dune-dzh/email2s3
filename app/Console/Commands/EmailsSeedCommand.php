<?php

namespace App\Console\Commands;

use App\Seeders\EmailSeeder;
use Illuminate\Console\Command;

class EmailsSeedCommand extends Command
{
    protected $signature = 'emails:seed {--records=100000 : Number of email records to seed}';

    protected $description = 'Seed the database with emails and attachments (supports --records)';

    public function handle(EmailSeeder $seeder): int
    {
        $records = (int) $this->option('records');
        if ($records <= 0) {
            $records = 100_000;
        }

        $this->info("Seeding database with {$records} email records...");

        $seeder->run($records);

        $this->info('Seeding completed.');

        return self::SUCCESS;
    }
}
