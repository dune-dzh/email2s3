<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($request === null || $request->expectsJson()) {
                return null;
            }
            $message = $e->getMessage();
            $file = $e->getFile();
            $line = $e->getLine();
            $class = get_class($e);
            $debug = function_exists('config') ? config('app.debug', false) : (bool) env('APP_DEBUG', false);
            $trace = $debug ? $e->getTraceAsString() : null;
            // Do not use response()->view() here; when bindings (view, cache, etc.) are missing it would throw again.
            $body = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body style="font-family:system-ui;margin:2rem;background:#f8fafc;color:#1e293b;">';
            $body .= '<h1 style="color:#dc2626;">Application Error</h1>';
            $body .= '<p><strong>' . htmlspecialchars($class) . '</strong></p>';
            $body .= '<p>' . htmlspecialchars($message) . '</p>';
            $body .= '<p style="color:#64748b;font-size:0.9rem;">' . htmlspecialchars($file) . ' (line ' . (int) $line . ')</p>';
            if ($trace !== null) {
                $body .= '<pre style="background:#e2e8f0;padding:1rem;overflow:auto;font-size:0.75rem;">' . htmlspecialchars($trace) . '</pre>';
            }
            $body .= '</body></html>';
            return response($body, 500, ['Content-Type' => 'text/html; charset=utf-8']);
        });
    })
    ->create();

