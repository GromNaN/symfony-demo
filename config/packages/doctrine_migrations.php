<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'doctrine_migrations' => [
        'migrations_paths' => [
            // namespace is arbitrary but should be different from App\Migrations
            // as migrations classes should NOT be autoloaded
            'DoctrineMigrations' => param('kernel.project_dir').'/migrations',
        ],
        'enable_profiler' => false,
    ],
]);
