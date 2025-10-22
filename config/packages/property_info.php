<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'property_info' => [
            'with_constructor_extractor' => true,
        ],
    ],
]);
