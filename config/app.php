<?php

return [
    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL') ?: 'http://localhost:8080',

    'timezone' => 'UTC',

    'locale' => 'en',

    'fallback_locale' => 'en',

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    'providers' => [
        /*
         * Core providers (ensure binding when discovery is incomplete or cached)
         */
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,
        Laravel\Reverb\ReverbServiceProvider::class,
        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\DoctrineServiceProvider::class,
    ],
];

