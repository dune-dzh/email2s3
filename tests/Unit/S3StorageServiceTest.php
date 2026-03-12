<?php

namespace Tests\Unit;

use App\Services\S3StorageService;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class S3StorageServiceTest extends TestCase
{
    public function test_upload_email_body_builds_expected_key_and_calls_s3(): void
    {
        $client = $this->createMock(S3Client::class);

        $client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function (array $args) {
                $this->assertSame('email2s3', $args['Bucket']);
                $this->assertSame('123/123.html', $args['Key']);
                $this->assertSame('text/html; charset=utf-8', $args['ContentType']);
                $this->assertSame('<html>body</html>', $args['Body']);

                return true;
            }));

        $service = $this->createServiceWithClient($client);

        $path = $service->uploadEmailBody(123, '<html>body</html>');

        $this->assertSame('/123/123.html', $path);
    }

    public function test_upload_attachment_builds_expected_key_and_calls_s3(): void
    {
        $client = $this->createMock(S3Client::class);

        $client->expects($this->once())
            ->method('putObject')
            ->with($this->callback(function (array $args) {
                $this->assertSame('email2s3', $args['Bucket']);
                $this->assertSame('42/test.txt', $args['Key']);
                $this->assertIsResource($args['Body']);

                return true;
            }));

        $tmpFile = tempnam(sys_get_temp_dir(), 'attachment_');
        file_put_contents($tmpFile, 'data');
        $renamed = $tmpFile . '.txt';
        rename($tmpFile, $renamed);

        $service = $this->createServiceWithClient($client);
        $path = $service->uploadAttachment(42, $renamed);

        $this->assertSame('/42/' . basename($renamed), $path);
        @unlink($renamed);
    }

    private function createServiceWithClient(S3Client $client): S3StorageService
    {
        $service = new S3StorageService();
        $ref = new ReflectionClass($service);
        $propClient = $ref->getProperty('client');
        $propClient->setAccessible(true);
        $propClient->setValue($service, $client);

        $propBucket = $ref->getProperty('bucket');
        $propBucket->setAccessible(true);
        $propBucket->setValue($service, 'email2s3');

        return $service;
    }
}

