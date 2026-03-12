<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // When accessed via public IP/host (e.g. http://46.224.187.179:8080), generate URLs with that host
        // so redirects and links work instead of pointing to localhost.
        if (! $this->app->runningInConsole() && $this->app->bound('request')) {
            $request = $this->app['request'];
            $host = $request->getHost();
            if ($host !== 'localhost' && $host !== '127.0.0.1' && $host !== '') {
                URL::forceRootUrl($request->getSchemeAndHttpHost());
            }
        }
    }
}

