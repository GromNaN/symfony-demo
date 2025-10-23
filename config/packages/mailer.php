<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'mailer' => [
            'dsn' => env('MAILER_DSN'),
        ],
    ],
    'when@dev' => [
        'framework' => [
            'mailer' => [
                // this disables delivery of messages entirely
                'dsn' => 'null://null',
            ],
        ],
    ],
    'when@test' => [
        'framework' => [
            'mailer' => [
                // this disables delivery of messages entirely
                'dsn' => 'null://null',
            ],
        ],
    ],
]);
