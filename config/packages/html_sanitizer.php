<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'html_sanitizer' => [
            'sanitizers' => [
                'default' => [
                    'allow_safe_elements' => true,
                ],
            ],
        ],
    ],
]);
