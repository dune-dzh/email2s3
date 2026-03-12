<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

try {
    $app = require_once __DIR__.'/../bootstrap/app.php';

    $kernel = $app->handleRequest(
        Request::capture()
    );

    $response = $kernel->toResponse();

    $response->send();

    $kernel->terminate($response);
} catch (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        $msg = htmlspecialchars($e->getMessage());
        $file = htmlspecialchars($e->getFile());
        $line = (int) $e->getLine();
        $class = htmlspecialchars(get_class($e));
        $trace = htmlspecialchars($e->getTraceAsString());
        echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><title>Error</title></head><body style=\"font-family:system-ui;margin:2rem;background:#f8fafc;color:#1e293b;\">";
        echo "<h1 style=\"color:#dc2626;\">Application Error</h1>";
        echo "<p><strong>{$class}</strong></p>";
        echo "<p>{$msg}</p>";
        echo "<p style=\"color:#64748b;font-size:0.9rem;\">{$file} (line {$line})</p>";
        echo "<pre style=\"background:#e2e8f0;padding:1rem;overflow:auto;font-size:0.75rem;\">{$trace}</pre>";
        echo "</body></html>";
    }
    exit(1);
}

