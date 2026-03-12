<?php

namespace App\Providers;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Support\ServiceProvider;

class DoctrineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntityManagerInterface::class, function () {
            $factory = config('doctrine.create_entity_manager');

            return $factory();
        });

        $this->app->alias(EntityManagerInterface::class, 'doctrine.em');
    }

    public function boot(): void
    {
        //
    }
}

