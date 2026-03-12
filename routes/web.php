<?php

use App\Http\Controllers\EmailController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('emails.index');
});

Route::get('/emails', [EmailController::class, 'index'])->name('emails.index');
Route::get('/emails/stats', [EmailController::class, 'stats'])->name('emails.stats');
Route::get('/emails/{email}/body', [EmailController::class, 'getBody'])->name('emails.body');
Route::get('/emails/{email}/attachments/{file}', [EmailController::class, 'downloadAttachment'])->name('emails.attachments.download');

// When APP_DEBUG=true, show last lines of Laravel log to debug 500 errors (e.g. after opening / or /emails)
Route::get('/log-tail', function () {
    if (! config('app.debug')) {
        abort(404);
    }
    $path = storage_path('logs/laravel.log');
    if (! is_readable($path)) {
        return response('<pre>Log file not found or not readable.</pre>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
    $lines = @file($path);
    $content = is_array($lines) ? implode('', array_slice($lines, -100)) : '';
    return response('<pre>' . htmlspecialchars($content) . '</pre>', 200, ['Content-Type' => 'text/html; charset=utf-8']);
})->name('log-tail');

