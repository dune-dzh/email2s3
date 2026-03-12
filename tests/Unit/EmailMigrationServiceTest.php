<?php

namespace Tests\Unit;

use App\Domain\Email;
use App\Domain\File as AttachmentFile;
use App\Services\EmailMigrationService;
use App\Services\S3StorageService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EmailMigrationServiceTest extends TestCase
{
    public function test_migrate_updates_paths_and_state_and_deletes_files(): void
    {
        $email = new Email();
        $email->setClientId(1)
            ->setSenderEmail('a@example.com')
            ->setReceiverEmail('b@example.com')
            ->setSubject('Test')
            ->setBody('<html>Body</html>')
            ->setFileIds([1]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'f_');
        file_put_contents($tmpFile, 'content');

        $file = new AttachmentFile();
        $file->setName(basename($tmpFile))
            ->setPath($tmpFile)
            ->setSize(7)
            ->setType('txt');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')
            ->willReturnCallback(function (string $class, $id) use ($email, $file) {
                if ($class === Email::class) {
                    return $email;
                }

                if ($class === AttachmentFile::class) {
                    return $file;
                }

                return null;
            });

        $em->expects($this->atLeastOnce())->method('flush');

        $storage = $this->createMock(S3StorageService::class);
        $storage->expects($this->once())
            ->method('uploadEmailBody')
            ->with(123, '<html>Body</html>')
            ->willReturn('/123/123.html');

        $storage->expects($this->once())
            ->method('uploadAttachment')
            ->with(123, $tmpFile)
            ->willReturn('/123/' . basename($tmpFile));

        $service = new EmailMigrationService($em, $storage);
        $service->migrate(123);

        $this->assertSame('/123/123.html', $email->getBodyS3Path());
        $this->assertEquals(['/123/' . basename($tmpFile)], $email->getFileS3Paths());
        $this->assertSame(2, $email->getIsMigratedS3());
        $this->assertFileDoesNotExist($tmpFile);
    }
}

