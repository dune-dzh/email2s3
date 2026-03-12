<?php

namespace Tests\Unit;

use App\Domain\Email;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class RepositoriesTest extends TestCase
{
    public function test_email_repository_can_find_ids_for_publishing_like_query(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);

        $em->method('getRepository')
            ->with(Email::class)
            ->willReturn($repo);

        $repo->expects($this->once())
            ->method('findBy')
            ->with(
                ['isMigratedS3' => 0],
                ['id' => 'ASC'],
                1000,
                0
            )
            ->willReturn([]);

        $result = $repo->findBy(
            ['isMigratedS3' => 0],
            ['id' => 'ASC'],
            1000,
            0
        );

        $this->assertIsArray($result);
    }
}

