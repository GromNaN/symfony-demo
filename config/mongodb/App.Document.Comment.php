<?php

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;

assert($metadata instanceof ClassMetadata);

$loader = new \Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver(__DIR__ . '/../../src/Document');
$loader->loadMetadataForClass(\App\Document\Comment::class, $metadata);
