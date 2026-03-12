<?php

namespace App\Services;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use RuntimeException;

class S3StorageService extends BaseService
{
    private ?S3Client $client = null;

    private ?S3Client $publicClient = null;

    private string $bucket;

    private bool $bucketEnsured = false;

    public function __construct()
    {
        $this->bucket = env('MINIO_BUCKET', env('AWS_BUCKET', 'email2s3'));
    }

    /**
     * Ensure the configured bucket exists in S3/MinIO; create it if not.
     */
    private function ensureBucketExists(): void
    {
        if ($this->bucketEnsured) {
            return;
        }

        $client = $this->getClient();
        try {
            $client->headBucket(['Bucket' => $this->bucket]);
        } catch (S3Exception $e) {
            if ($e->getAwsErrorCode() === 'NotFound' || $e->getAwsErrorCode() === 'NoSuchBucket') {
                $client->createBucket(['Bucket' => $this->bucket]);
            } else {
                throw $e;
            }
        }
        $this->bucketEnsured = true;
    }

    private function getClient(): S3Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $key = env('MINIO_ROOT_USER', env('AWS_ACCESS_KEY_ID'));
        $secret = env('MINIO_ROOT_PASSWORD', env('AWS_SECRET_ACCESS_KEY'));
        if ($key === null || $key === '' || $secret === null || $secret === '') {
            throw new RuntimeException(
                'S3/MinIO credentials are not set. Set MINIO_ROOT_USER and MINIO_ROOT_PASSWORD (or AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY) in .env'
            );
        }

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'endpoint' => env('MINIO_ENDPOINT', 'http://minio:9000'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        return $this->client;
    }

    /**
     * S3 client for presigned URLs: uses MINIO_PUBLIC_ENDPOINT so the signature matches the Host the browser sends.
     */
    private function getPublicClient(): S3Client
    {
        if ($this->publicClient !== null) {
            return $this->publicClient;
        }
        $publicEndpoint = env('MINIO_PUBLIC_ENDPOINT');
        if ($publicEndpoint === null || $publicEndpoint === '') {
            return $this->getClient();
        }
        $key = env('MINIO_ROOT_USER', env('AWS_ACCESS_KEY_ID'));
        $secret = env('MINIO_ROOT_PASSWORD', env('AWS_SECRET_ACCESS_KEY'));
        if ($key === null || $key === '' || $secret === null || $secret === '') {
            return $this->getClient();
        }
        $this->publicClient = new S3Client([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'endpoint' => rtrim($publicEndpoint, '/'),
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);
        return $this->publicClient;
    }

    /**
     * Upload an email HTML body to S3/MinIO.
     * Uses a stream so the body is not duplicated in memory when building the request.
     */
    public function uploadEmailBody(int $emailId, string $html): string
    {
        $this->ensureBucketExists();
        $filename = $emailId . '.html';
        $key = $this->buildKey($emailId, $filename);

        $length = strlen($html);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to create temp stream for email body');
        }
        fwrite($stream, $html);
        unset($html);
        rewind($stream);

        try {
            $this->getClient()->putObject([
                'Bucket' => $this->bucket,
                'Key' => ltrim($key, '/'),
                'Body' => $stream,
                'ContentLength' => $length,
                'ContentType' => 'text/html; charset=utf-8',
            ]);
        } catch (S3Exception $e) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('Failed to upload email body to S3: ' . $e->getMessage(), 0, $e);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $key;
    }

    /**
     * Upload an attachment file from local disk to S3/MinIO.
     *
     * @param int $emailId
     * @param string $filePath Absolute path to the local file
     */
    public function uploadAttachment(int $emailId, string $filePath): string
    {
        $this->ensureBucketExists();
        if (! is_file($filePath)) {
            throw new RuntimeException("Attachment file not found at path: {$filePath}");
        }

        $filename = basename($filePath);
        $key = $this->buildKey($emailId, $filename);

        $contentLength = filesize($filePath);
        $stream = fopen($filePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException("Unable to open attachment file for reading: {$filePath}");
        }

        try {
            $this->getClient()->putObject([
                'Bucket' => $this->bucket,
                'Key' => ltrim($key, '/'),
                'Body' => $stream,
                'ContentLength' => $contentLength,
            ]);
        } catch (S3Exception $e) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('Failed to upload attachment to S3: ' . $e->getMessage(), 0, $e);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $key;
    }

    private function buildKey(int $recordId, string $filename): string
    {
        return sprintf('/%d/%s', $recordId, $filename);
    }

    /**
     * Return a presigned URL to download an object from S3/MinIO (e.g. for migrated attachments).
     * When MINIO_PUBLIC_ENDPOINT is set, the URL is generated with that endpoint so the signature
     * matches the Host header the browser sends (fixes SignatureDoesNotMatch when URL was signed for minio:9000).
     */
    public function getDownloadUrl(string $key, int $expiresInSeconds = 3600): string
    {
        $key = ltrim($key, '/');
        $client = $this->getPublicClient();
        $cmd = $client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
        $request = $client->createPresignedRequest($cmd, "+{$expiresInSeconds} seconds");

        return (string) $request->getUri();
    }

    /**
     * Get object content from S3/MinIO (e.g. migrated email body).
     */
    public function getObjectContent(string $key): string
    {
        $key = ltrim($key, '/');
        $result = $this->getClient()->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
        $body = $result['Body'];
        return (string) $body;
    }
}

