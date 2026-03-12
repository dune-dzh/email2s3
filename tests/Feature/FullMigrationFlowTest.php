<?php

namespace Tests\Feature;

use App\Console\Commands\MigrationWorkerCommand;
use App\Services\EmailMigrationService;
use App\Services\MigrationPublisherService;
use App\Services\S3StorageService;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\TestCase;

class FullMigrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_migration_flow_with_mocked_minio_and_rabbitmq(): void
    {
        // Prepare a single email and file.
        DB::table('files')->insert([
            'id' => 1,
            'name' => 'test.txt',
            'path' => storage_path('app/test_full_flow.txt'),
            'size' => 10,
            'type' => 'txt',
        ]);

        file_put_contents(storage_path('app/test_full_flow.txt'), 'content');

        DB::table('emails')->insert([
            'id' => 1,
            'client_id' => 1,
            'loan_id' => null,
            'email_template_id' => null,
            'receiver_email' => 'b@example.com',
            'sender_email' => 'a@example.com',
            'subject' => 'Subject',
            'body' => '<html>Body</html>',
            'file_ids' => json_encode([1]),
            'body_s3_path' => null,
            'file_s3_paths' => null,
            'is_migrated_s3' => 0,
            'created_at' => now(),
            'sent_at' => null,
        ]);

        // Fake S3 storage.
        $s3 = $this->createMock(S3StorageService::class);
        $s3->method('uploadEmailBody')->willReturn('/1/1.html');
        $s3->method('uploadAttachment')->willReturn('/1/test.txt');

        // Replace EmailMigrationService in the container to use mocked S3.
        $this->app->bind(EmailMigrationService::class, function ($app) use ($s3) {
            return new EmailMigrationService(
                $app->make(EntityManagerInterface::class),
                $s3
            );
        });

        // Mock RabbitMQ connection and channel for publisher and worker.
        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('queue_declare');

        $publishedMessages = [];
        $channel->method('basic_publish')
            ->willReturnCallback(function (AMQPMessage $msg) use (&$publishedMessages) {
                $publishedMessages[] = $msg->getBody();
            });

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->method('channel')->willReturn($channel);

        $this->app->bind(AMQPStreamConnection::class, fn () => $connection);

        // Run publisher once; it should try to publish IDs, but we stubbed connection.
        /** @var MigrationPublisherService $publisher */
        $publisher = $this->app->make(MigrationPublisherService::class);
        $publisher->publishNextBatch();

        // Simulate worker consuming one published message.
        if (! empty($publishedMessages)) {
            $payload = json_decode($publishedMessages[0], true, 512, JSON_THROW_ON_ERROR);
            $emailId = $payload['email_id'] ?? null;

            /** @var EmailMigrationService $migrationService */
            $migrationService = $this->app->make(EmailMigrationService::class);
            $migrationService->migrate($emailId);
        }

        $email = DB::table('emails')->where('id', 1)->first();

        $this->assertNotNull($email->body_s3_path);
        $this->assertEquals(2, $email->is_migrated_s3);
    }
}

