<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'twig_component' => [
        'anonymous_template_directory' => 'components/',
        'defaults' => [
            // Namespace & directory for components
            'App\\Twig\\Components\\' => 'components/',
        ],
    ],
]);
