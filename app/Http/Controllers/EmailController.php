<?php

namespace App\Http\Controllers;

use App\Services\MigrationStatsService;
use App\Services\S3StorageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailController extends Controller
{
    public function __construct(
        private readonly S3StorageService $s3Storage,
        private readonly MigrationStatsService $statsService,
    ) {
    }

    public function index(Request $request): View|Response
    {
        $sender = trim((string) $request->input('sender_email', ''));
        $receiver = trim((string) $request->input('receiver_email', ''));
        $dateFrom = trim((string) $request->input('date_from', ''));
        $dateTo = trim((string) $request->input('date_to', ''));

        $allowedSort = ['id', 'sender_email', 'receiver_email', 'subject', 'created_at'];
        $sort = $request->input('sort', 'id');
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'id';
        }
        $dir = strtolower((string) $request->input('dir', 'asc'));
        if ($dir !== 'asc' && $dir !== 'desc') {
            $dir = 'asc';
        }

        $query = DB::table('emails');

        if ($sender !== '') {
            $query->where('sender_email', 'ILIKE', '%' . $sender . '%');
        }

        if ($receiver !== '') {
            $query->where('receiver_email', 'ILIKE', '%' . $receiver . '%');
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        try {
            $page = max(1, (int) $request->input('page', 1));
            $emails = $query
                ->orderBy($sort, $dir)
                ->paginate(20, ['*'], 'page', $page)
                ->appends($request->query());

            $total = (int) DB::table('emails')->count();
            $migrated = (int) DB::table('emails')->where('is_migrated_s3', 2)->count();
            $migrating = (int) DB::table('emails')->where('is_migrated_s3', 1)->count();
            $pending = (int) DB::table('emails')->where('is_migrated_s3', 0)->count();
        } catch (QueryException $e) {
            return $this->databaseErrorView($e);
        }

        $fileMap = $this->buildFileMapForEmails($emails);

        if ($request->boolean('partial')) {
            return response()->view('emails.partials.list', [
                'emails' => $emails,
                'fileMap' => $fileMap,
                'sort' => $sort,
                'sortDir' => $dir,
            ])->header('Content-Type', 'text/html; charset=utf-8');
        }

        return view('emails.index', [
            'emails' => $emails,
            'fileMap' => $fileMap,
            'filters' => [
                'sender_email' => $sender,
                'receiver_email' => $receiver,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'sort' => $sort,
            'sortDir' => $dir,
            'stats' => [
                'total' => $total,
                'migrated' => $migrated,
                'migrating' => $migrating,
                'pending' => $pending,
            ],
        ]);
    }

    /**
     * JSON endpoint for migration stats (used by dashboard polling when WebSocket is unavailable).
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->statsService->gather());
    }

    /**
     * Download an attachment: stream from local storage (not migrated) or redirect to S3 presigned URL (migrated).
     */
    public function downloadAttachment(int $email, int $file): Response|StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        $emailRow = DB::table('emails')->where('id', $email)->first();
        if ($emailRow === null) {
            abort(404, 'Email not found');
        }

        $fileIds = $emailRow->file_ids ? (array) json_decode($emailRow->file_ids, true) : [];
        $fileIndex = array_search($file, array_map('intval', $fileIds), true);
        if ($fileIndex === false) {
            abort(404, 'Attachment not found for this email');
        }

        $fileRow = DB::table('files')->where('id', $file)->first();
        if ($fileRow === null) {
            abort(404, 'File not found');
        }

        $isMigrated = (int) $emailRow->is_migrated_s3 === 2;

        if ($isMigrated) {
            $s3Paths = $emailRow->file_s3_paths ? (array) json_decode($emailRow->file_s3_paths, true) : [];
            $s3Key = $s3Paths[$fileIndex] ?? null;
            if ($s3Key === null) {
                abort(404, 'S3 path not found for this attachment');
            }
            try {
                $url = $this->s3Storage->getDownloadUrl($s3Key, 3600);
                return redirect()->away($url);
            } catch (\Throwable) {
                abort(502, 'Unable to generate download link');
            }
        }

        $path = $fileRow->path;
        $storageRoot = realpath(storage_path('app'));
        if ($storageRoot === false) {
            abort(500, 'Storage path not configured');
        }
        $realPath = realpath($path);
        if ($realPath === false || ! is_file($realPath)) {
            abort(404, 'File not found or not readable');
        }
        if (! str_starts_with(str_replace('\\', '/', $realPath), str_replace('\\', '/', $storageRoot))) {
            abort(403, 'Access denied');
        }

        $name = $fileRow->name ?? basename($path);
        $mime = $this->mimeForExtension($fileRow->type ?? pathinfo($name, PATHINFO_EXTENSION));

        return response()->streamDownload(function () use ($realPath) {
            $stream = fopen($realPath, 'rb');
            if ($stream) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $name, [
            'Content-Type' => $mime,
        ]);
    }

    private function mimeForExtension(string $ext): string
    {
        return match (strtolower($ext)) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'ppt', 'pptx' => 'application/vnd.ms-powerpoint',
            default => 'application/octet-stream',
        };
    }

    private function buildFileMapForEmails($emails): array
    {
        $fileIds = [];
        foreach ($emails as $email) {
            $ids = $email->file_ids ? (array) json_decode($email->file_ids, true) : [];
            foreach ($ids as $id) {
                $fileIds[] = (int) $id;
            }
        }
        $fileIds = array_unique($fileIds);
        if (empty($fileIds)) {
            return [];
        }

        $files = DB::table('files')->whereIn('id', $fileIds)->get();
        $map = [];
        foreach ($files as $f) {
            $map[(int) $f->id] = [
                'name' => $f->name,
                'size' => (int) $f->size,
            ];
        }
        return $map;
    }

    /**
     * Return email body as JSON (html + plain_text) for the body viewer modal.
     * Uses body from DB when present; when body has been cleared after migration, loads from S3 using body_s3_path.
     */
    public function getBody(int $email): JsonResponse
    {
        $emailRow = DB::table('emails')->where('id', $email)->first();
        if ($emailRow === null) {
            abort(404, 'Email not found');
        }

        $html = $emailRow->body;
        $source = 'database';

        if ($html === null || $html === '') {
            if (! empty($emailRow->body_s3_path)) {
                try {
                    $html = $this->s3Storage->getObjectContent($emailRow->body_s3_path);
                    $source = 's3';
                } catch (\Throwable) {
                    abort(502, 'Unable to load body from storage');
                }
            }
        }

        $html = $html ?? '';
        $plainText = trim(strip_tags($html));
        $plainText = html_entity_decode($plainText, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return response()->json([
            'html' => $html,
            'plain_text' => $plainText,
            'subject' => $emailRow->subject ?? '',
            'source' => $source,
        ]);
    }

    private function databaseErrorView(QueryException $e): View
    {
        $message = $e->getMessage();
        $hint = (str_contains($e->getMessage(), 'does not exist') || str_contains($e->getMessage(), 'relation "emails"'))
            ? 'Run: ./run.sh (or docker compose exec php-fpm php artisan db:ensure-schema)'
            : 'Check database connection and that the emails table exists.';

        return view('emails.error', [
            'message' => $message,
            'hint' => $hint,
        ]);
    }
}

