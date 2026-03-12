<?php

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\EntityManager;

return [
    'dev_mode' => env('APP_DEBUG', false),

    'proxy_dir' => storage_path('framework/doctrine/proxies'),

    'entity_paths' => [
        app_path('Domain'),
    ],

    'connection' => [
        'driver'   => 'pdo_pgsql',
        'host'     => env('DB_HOST', 'postgres'),
        'port'     => (int) env('DB_PORT', 5432),
        'dbname'   => env('DB_DATABASE', 'email2s3'),
        'user'     => env('DB_USERNAME', 'email2s3'),
        'password' => env('DB_PASSWORD', 'secret'),
    ],

    'create_entity_manager' => static function (): EntityManager {
        $config = config('doctrine');

        $isDevMode  = (bool) ($config['dev_mode'] ?? false);
        $proxyDir   = $config['proxy_dir'] ?? null;
        $entityDirs = $config['entity_paths'] ?? [];
        $connectionParams = $config['connection'] ?? [];

        $ormConfig = ORMSetup::createAttributeMetadataConfiguration(
            $entityDirs,
            $isDevMode,
            $proxyDir
        );

        $connection = DriverManager::getConnection($connectionParams, $ormConfig);

        return new EntityManager($connection, $ormConfig);
    },
];

