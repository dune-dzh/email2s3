<?php

namespace App\Console\Commands;

use App\Services\EmailMigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class MigrationWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * You can optionally override the worker count:
     * php artisan migration:worker --workers=4
     */
    protected $signature = 'migration:worker {--workers=}';

    /**
     * The console command description.
     */
    protected $description = 'Run RabbitMQ workers that migrate emails to S3/MinIO';

    private const QUEUE_NAME = 'email_migration_queue';

    public function __construct(
        private readonly EmailMigrationService $migrationService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cpuCount = $this->getCpuCount();
        $defaultWorkers = max(1, $cpuCount - 1);
        $workers = (int) ($this->option('workers') ?: $defaultWorkers);

        if ($workers <= 0) {
            $workers = 1;
        }

        $this->info(sprintf('Starting migration worker with %d worker(s)', $workers));

        // Avoid memory exhaustion when uploading large bodies/attachments to S3 (Guzzle buffers otherwise).
        $limit = env('MIGRATION_WORKER_MEMORY_LIMIT', '256M');
        if ($limit !== '' && $limit !== '0') {
            @ini_set('memory_limit', $limit);
        }

        // Simple implementation: single PHP process with a single consumer loop.
        // Worker count is logged and can be used later for process management / scaling.
        $this->runWorkerLoop(1);

        return self::SUCCESS;
    }

    private function runWorkerLoop(int $workerId): void
    {
        $this->info("Worker {$workerId} starting main loop");

        while (true) {
            try {
                $this->consumeOnce($workerId);
            } catch (AMQPIOException $e) {
                Log::warning('AMQP IO exception in worker loop, will reconnect', [
                    'worker_id' => $workerId,
                    'error' => $e->getMessage(),
                ]);
                sleep(5);
            } catch (Throwable $e) {
                Log::error('Unexpected error in worker loop', [
                    'worker_id' => $workerId,
                    'error' => $e->getMessage(),
                ]);
                sleep(5);
            }
        }
    }

    private function consumeOnce(int $workerId): void
    {
        $connection = $this->createAmqpConnection();
        $channel = $connection->channel();

        $channel->queue_declare(
            self::QUEUE_NAME,
            false,
            true,
            false,
            false
        );

        // Fair dispatch: one message at a time per worker.
        $channel->basic_qos(null, 1, null);

        $callback = function (AMQPMessage $message) use ($workerId, $channel): void {
            $body = $message->getBody();

            try {
                $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                $emailId = (int) ($payload['email_id'] ?? 0);

                if ($emailId <= 0) {
                    Log::warning('Received invalid email_id in migration message', [
                        'worker_id' => $workerId,
                        'payload' => $payload,
                    ]);
                    $channel->basic_ack($message->getDeliveryTag());

                    return;
                }

                Log::info('Starting email migration', [
                    'worker_id' => $workerId,
                    'email_id' => $emailId,
                ]);

                $this->migrationService->migrate($emailId);

                Log::info('Email migration completed', [
                    'worker_id' => $workerId,
                    'email_id' => $emailId,
                ]);

                $channel->basic_ack($message->getDeliveryTag());
            } catch (Throwable $e) {
                Log::error('Email migration failed in worker', [
                    'worker_id' => $workerId,
                    'error' => $e->getMessage(),
                    'body' => $body,
                ]);

                // Nack and requeue the message for retry.
                $channel->basic_nack($message->getDeliveryTag(), false, true);
            } finally {
                gc_collect_cycles();
            }
        };

        $channel->basic_consume(
            self::QUEUE_NAME,
            '',
            false,
            false,
            false,
            false,
            $callback
        );

        while ($channel->is_consuming()) {
            $channel->wait(null, false, 300);
        }

        $channel->close();
        $connection->close();
    }

    private function createAmqpConnection(): AMQPStreamConnection
    {
        $host = env('RABBITMQ_HOST', 'rabbitmq');
        $port = (int) env('RABBITMQ_PORT', 5672);
        $user = env('RABBITMQ_USER', 'guest');
        $password = env('RABBITMQ_PASSWORD', 'guest');
        $vhost = env('RABBITMQ_VHOST', '/');

        return new AMQPStreamConnection($host, $port, $user, $password, $vhost);
    }

    private function getCpuCount(): int
    {
        if (function_exists('shell_exec')) {
            $nproc = trim((string) shell_exec('nproc 2>/dev/null'));
            if ($nproc !== '' && ctype_digit($nproc)) {
                return (int) $nproc;
            }
        }

        return 2;
    }
}

