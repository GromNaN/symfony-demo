<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'default_locale' => param('app.locale'),
        'translator' => [
            'default_path' => param('kernel.project_dir').'/translations',
            'providers' => [
                // Uncomment and configure providers as needed
                // 'crowdin' => ['dsn' => env('CROWDIN_DSN')],
                // 'loco' => ['dsn' => env('LOCO_DSN')],
                // 'lokalise' => ['dsn' => env('LOKALISE_DSN')],
                // 'phrase' => ['dsn' => env('PHRASE_DSN')],
            ],
        ],
    ],
]);
