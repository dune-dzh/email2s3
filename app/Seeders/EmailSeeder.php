<?php

namespace App\Seeders;

use Faker\Factory as FakerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class EmailSeeder
{
    public function run(int $records): void
    {
        if ($records <= 0) {
            $records = 100_000;
        }

        $faker = FakerFactory::create();

        $baseDir = storage_path('app/email_attachments');
        if (! File::exists($baseDir)) {
            File::makeDirectory($baseDir, 0775, true);
        }

        $runId = 'run_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $attachmentsDir = $baseDir . DIRECTORY_SEPARATOR . $runId;
        File::makeDirectory($attachmentsDir, 0775, true);

        $extensions = ['doc', 'pdf', 'ppt', 'txt', 'csv'];
        $namePrefixes = ['invoice', 'report', 'document', 'statement', 'contract', 'summary', 'data_export', 'presentation', 'attachment', 'letter', 'form'];
        $emailsPerBatch = 500;
        $totalBatches = (int) ceil($records / $emailsPerBatch);

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $batchSubdir = $attachmentsDir . DIRECTORY_SEPARATOR . 'batch_' . $batch;
            if (! File::exists($batchSubdir)) {
                File::makeDirectory($batchSubdir, 0775, true);
            }

            DB::transaction(function () use (
                $faker,
                $batchSubdir,
                $extensions,
                $namePrefixes,
                $emailsPerBatch,
                $records,
                $batch
            ) {
                $startIndex = $batch * $emailsPerBatch;
                $remaining = $records - $startIndex;
                $currentBatchSize = $remaining > $emailsPerBatch ? $emailsPerBatch : $remaining;

                for ($i = 0; $i < $currentBatchSize; $i++) {
                    $attachmentCount = random_int(1, 3);
                    $fileIds = [];

                    for ($j = 0; $j < $attachmentCount; $j++) {
                        $ext = $extensions[array_rand($extensions)];
                        $fileSize = random_int(10 * 1024, 1024 * 1024);

                        $prefix = $namePrefixes[array_rand($namePrefixes)];
                        $uniqueNum = $i * 3 + $j;
                        $filename = $prefix . '_' . $uniqueNum . '.' . $ext;
                        $filePath = $batchSubdir . DIRECTORY_SEPARATOR . $filename;

                        $data = self::generateFileContent($ext, $fileSize);
                        file_put_contents($filePath, $data);

                        $fileId = DB::table('files')->insertGetId([
                            'name' => $filename,
                            'path' => $filePath,
                            'size' => strlen($data),
                            'type' => $ext,
                        ]);

                        $fileIds[] = $fileId;
                    }

                    $bodySize = 10 * 1024 + random_int(0, 40 * 1024);
                    $body = self::generateHtmlBody($faker, $bodySize);

                    DB::table('emails')->insert([
                        'client_id' => $faker->numberBetween(1, 1000),
                        'loan_id' => $faker->optional()->numberBetween(1, 5000),
                        'email_template_id' => $faker->optional()->numberBetween(1, 100),
                        'receiver_email' => $faker->safeEmail(),
                        'sender_email' => $faker->safeEmail(),
                        'subject' => $faker->sentence(6),
                        'body' => $body,
                        'file_ids' => json_encode($fileIds),
                        'created_at' => $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d H:i:sP'),
                        'sent_at' => $faker->optional()->dateTimeBetween('-2 years', 'now')?->format('Y-m-d H:i:sP'),
                    ]);
                }
            });
        }
    }

    private static function generateHtmlBody($faker, int $minBytes): string
    {
        $content = '<html><body>';
        while (strlen($content) < $minBytes) {
            $content .= '<p>' . e($faker->paragraph(5)) . '</p>';
        }
        $content .= '</body></html>';

        return $content;
    }

    private static function generateFileContent(string $ext, int $size): string
    {
        $header = '';
        switch (strtolower($ext)) {
            case 'txt':
                $header = "TXT FILE\n";
                break;
            case 'csv':
                $header = "col1,col2,col3\n";
                break;
            case 'pdf':
                $header = "%PDF-1.4\n";
                break;
            case 'doc':
                $header = "DOCFILE\n";
                break;
            case 'ppt':
                $header = "PPTFILE\n";
                break;
        }

        $remaining = max(0, $size - strlen($header));
        return $header . random_bytes($remaining);
    }
}
