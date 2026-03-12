<?php

namespace App\Services;

use App\Domain\Email;
use App\Domain\File;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EmailMigrationService extends BaseService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly S3StorageService $storageService,
    ) {
    }

    /**
     * Migrate a single email record to S3/MinIO.
     */
    public function migrate(int $emailId): void
    {
        /** @var Email|null $email */
        $email = $this->entityManager->find(Email::class, $emailId);

        if ($email === null) {
            Log::warning('Email not found for migration', ['email_id' => $emailId]);

            return;
        }

        if ($email->getIsMigratedS3() === 2) {
            // Already migrated, idempotent no-op.
            return;
        }

        // Mark as migrating (1)
        $email->setIsMigratedS3(1);
        $this->entityManager->flush();

        $uploadedAttachmentPaths = [];
        $localAttachmentPaths = [];

        try {
            // 1) Upload body if present, then remove from DB
            if ($email->getBody() !== null) {
                $bodyPath = $this->storageService->uploadEmailBody($emailId, $email->getBody());
                $email->setBodyS3Path($bodyPath);
                $email->setBody(null);
            }

            // 2) Upload attachments
            $fileIds = $email->getFileIds() ?? [];

            if (! is_array($fileIds)) {
                $fileIds = [];
            }

            foreach ($fileIds as $fileId) {
                /** @var File|null $file */
                $file = $this->entityManager->find(File::class, (int) $fileId);

                if ($file === null) {
                    Log::warning('File referenced by email not found', [
                        'email_id' => $emailId,
                        'file_id' => $fileId,
                    ]);

                    continue;
                }

                $localPath = $file->getPath();
                $localAttachmentPaths[] = $localPath;

                $s3Path = $this->storageService->uploadAttachment($emailId, $localPath);
                $uploadedAttachmentPaths[] = $s3Path;
            }

            // 3) Update paths on email record
            if (! empty($uploadedAttachmentPaths)) {
                $email->setFileS3Paths($uploadedAttachmentPaths);
            }

            $this->entityManager->flush();

            // 4) Delete local files after successful uploads and DB update
            foreach ($localAttachmentPaths as $path) {
                if (is_file($path) && ! @unlink($path)) {
                    throw new RuntimeException("Failed to delete local attachment file: {$path}");
                }
            }

            // 5) Mark as migrated (2)
            $email->setIsMigratedS3(2);
            $this->entityManager->flush();
        } catch (Throwable $e) {
            // On error: restore is_migrated_s3 = 0 and do not delete local files
            $email->setIsMigratedS3(0);

            try {
                $this->entityManager->flush();
            } catch (Throwable $flushException) {
                Log::error('Failed to flush email migration rollback', [
                    'email_id' => $emailId,
                    'exception' => $flushException->getMessage(),
                ]);
            }

            Log::error('Email migration failed', [
                'email_id' => $emailId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

