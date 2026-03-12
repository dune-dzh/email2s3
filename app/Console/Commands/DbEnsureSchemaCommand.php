<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DbEnsureSchemaCommand extends Command
{
    protected $signature = 'db:ensure-schema';

    protected $description = 'Create emails, files, and migration_offsets tables if they do not exist';

    public function handle(): int
    {
        if (! Schema::hasTable('migrations')) {
            $this->info('Creating migrations table...');
            Schema::create('migrations', function ($table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
            $this->info('migrations table created.');
        }

        if (! Schema::hasTable('emails')) {
            $this->info('Creating emails table...');
            Schema::create('emails', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('client_id');
                $table->unsignedBigInteger('loan_id')->nullable();
                $table->unsignedBigInteger('email_template_id')->nullable();
                $table->string('receiver_email', 255);
                $table->string('sender_email', 255);
                $table->string('subject', 255);
                $table->text('body')->nullable();
                $table->json('file_ids')->nullable();
                $table->string('body_s3_path', 512)->nullable();
                $table->json('file_s3_paths')->nullable();
                $table->unsignedSmallInteger('is_migrated_s3')->default(0);
                $table->timestampTz('created_at');
                $table->timestampTz('sent_at')->nullable();
                $table->index('client_id');
                $table->index('loan_id');
                $table->index('email_template_id');
            });
            $this->info('emails table created.');
        } else {
            $this->line('emails table already exists.');
        }

        if (! Schema::hasTable('files')) {
            $this->info('Creating files table...');
            Schema::create('files', function ($table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->string('path', 512);
                $table->unsignedBigInteger('size');
                $table->string('type', 100);
                $table->index('type');
            });
            $this->info('files table created.');
        } else {
            $this->line('files table already exists.');
        }

        if (! Schema::hasTable('migration_offsets')) {
            $this->info('Creating migration_offsets table...');
            Schema::create('migration_offsets', function ($table) {
                $table->string('name', 128)->primary();
                $table->unsignedBigInteger('last_published_id')->default(0);
            });
            $this->info('migration_offsets table created.');
        } else {
            $this->line('migration_offsets table already exists.');
        }

        if (! Schema::hasTable('cache')) {
            $this->info('Creating cache table (for CACHE_DRIVER=database or Reverb)...');
            Schema::create('cache', function ($table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
            $this->info('cache table created.');
        } else {
            $this->line('cache table already exists.');
        }

        $this->info('Schema ensure complete.');

        return self::SUCCESS;
    }
}
