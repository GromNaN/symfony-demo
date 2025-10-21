<?php

use Symfony\Component\Config\Builder\JsonSchemaGenerator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

require_once __DIR__.'/../vendor/autoload.php';

$bundles = require __DIR__.'/bundles.php';
$jsonschemaGenerator = new JsonSchemaGenerator();
$container = new ContainerBuilder();
// The debug parameter can vary based on the environment
$container->setParameter('kernel.debug', true);

$schema = [
    '$schema' => 'http://json-schema.org/draft-07/schema#',
    'type' => 'object',
    'additionalProperties' => false,
    'properties' => [],
    '$defs' => [],
];

$allEnvs = ['dev', 'prod', 'test'];
foreach ($allEnvs as $env) {
    $schema['properties']['when@' . $env] = [
        'type' => 'object',
        'properties' => [],
        'additionalProperties' => false,
    ];
}

foreach ($bundles as $class => $envs) {
    $bundle = new $class();
    assert($bundle instanceof BundleInterface);
    $extension = $bundle->getContainerExtension();
    if ($extension instanceof ConfigurationExtensionInterface) {
        $configuration = $extension->getConfiguration([], $container);
        if ($configuration) {
            $node = $configuration->getConfigTreeBuilder()->buildTree();

            $name = $node->getName();
            $ref = ['$ref' => '#/$defs/'.$name];
            $schema['$defs'][$name] = $jsonschemaGenerator->generate($node);

            if ($envs === ['all' => true]) {
                $schema['properties'][$name] = $ref;
                $envs = $allEnvs;
            } else {
                $envs = array_keys(array_filter($envs));
            }

            foreach ($envs as $env) {
                $schema['properties']['when@'.$env]['properties'][$name] = $ref;
            }
        }
    }
}

file_put_contents(__DIR__.'/schema.json', json_encode($schema, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));

