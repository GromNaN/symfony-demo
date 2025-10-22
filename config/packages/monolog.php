<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        'channels' => [
            'deprecation', // Deprecations are logged in the dedicated "deprecation" channel when it exists
        ],
    ],
    'when@dev' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'stream',
                    'path' => param('kernel.logs_dir').'/'.param('kernel.environment').'.log',
                    'level' => 'debug',
                    'channels' => ['!event'],
                ],
                // Uncomment to get logging in your browser
                // You may have to allow bigger header sizes in your Web server configuration
                // 'firephp' => [
                //     'type' => 'firephp',
                //     'level' => 'info',
                // ],
                // 'chromephp' => [
                //     'type' => 'chromephp',
                //     'level' => 'info',
                // ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => ['!event', '!doctrine', '!console'],
                ],
            ],
        ],
    ],
    'when@test' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => ['!event'],
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => param('kernel.logs_dir').'/'.param('kernel.environment').'.log',
                    'level' => 'debug',
                ],
            ],
        ],
    ],
    'when@prod' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'error',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'buffer_size' => 50, // How many messages should be saved? Prevent memory leaks
                ],
                'nested' => [
                    'type' => 'stream',
                    'path' => 'php://stderr',
                    'level' => 'debug',
                    'formatter' => 'monolog.formatter.json',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => ['!event', '!doctrine'],
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'channels' => ['deprecation'],
                    'path' => 'php://stderr',
                    'formatter' => 'monolog.formatter.json',
                ],
            ],
        ],
    ],
]);
