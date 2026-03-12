<?php

namespace App\Services;

use App\Domain\Email;
use Doctrine\ORM\EntityManagerInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrationPublisherService extends BaseService
{
    private const QUEUE_NAME = 'email_migration_queue';
    private const OFFSET_KEY = 'email_s3_migration_last_published_id';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Publish a batch of email IDs to RabbitMQ.
     */
    public function publishNextBatch(): int
    {
        $batchSize = (int) (config('migration.publisher_batch_size', env('MIGRATION_PUBLISH_BATCH_SIZE', 1000)));
        if ($batchSize <= 0) {
            $batchSize = 1000;
        }

        $lastId = $this->getLastPublishedId();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e.id')
            ->from(Email::class, 'e')
            ->where('e.id > :lastId')
            ->andWhere('e.isMigratedS3 = 0')
            ->orderBy('e.id', 'ASC')
            ->setMaxResults($batchSize)
            ->setParameter('lastId', $lastId);

        $ids = array_map(
            static fn (array $row) => (int) $row['id'],
            $qb->getQuery()->getArrayResult()
        );

        if (empty($ids)) {
            return 0;
        }

        $connection = $this->createAmqpConnection();
        $channel = $connection->channel();

        $channel->queue_declare(
            self::QUEUE_NAME,
            false,
            true,
            false,
            false
        );

        $maxId = $lastId;

        foreach ($ids as $id) {
            $payload = json_encode(['email_id' => $id], JSON_THROW_ON_ERROR);
            $message = new AMQPMessage($payload, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);

            $channel->basic_publish($message, '', self::QUEUE_NAME);

            $maxId = max($maxId, $id);
        }

        $channel->close();
        $connection->close();

        $this->storeLastPublishedId($maxId);

        Log::info('Published email migration batch', [
            'count' => count($ids),
            'last_published_id' => $maxId,
        ]);

        return count($ids);
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

    private function getLastPublishedId(): int
    {
        $row = DB::table('migration_offsets')
            ->where('name', self::OFFSET_KEY)
            ->first();

        if (! $row) {
            return 0;
        }

        return (int) ($row->last_published_id ?? 0);
    }

    private function storeLastPublishedId(int $id): void
    {
        DB::table('migration_offsets')->updateOrInsert(
            ['name' => self::OFFSET_KEY],
            ['last_published_id' => $id]
        );
    }
}

