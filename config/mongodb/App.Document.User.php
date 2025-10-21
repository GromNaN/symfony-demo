<?php

use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Types\Type;

assert($metadata instanceof ClassMetadata);

//$loader = new \Doctrine\ODM\MongoDB\Mapping\Driver\AttributeDriver(__DIR__ . '/../../src/Document');
//$loader->loadMetadataForClass(\App\Document\User::class, $metadata);

//dd($metadata);
$metadata->mapField([
    'name' => '_id',
    'fieldName' => 'id',
    'type' => Type::ID,
    'nullable' => false,
    'options' => [],
    'strategy' => ClassMetadata::GENERATOR_TYPE_AUTO,
    'notSaved' => false,
    'id' => true,
    'isCascadeRemove' => false,
    'isCascadePersist' => false,
    'isCascadeRefresh' => false,
    'isCascadeMerge' => false,
    'isCascadeDetach' => false,
    'isOwningSide' => true,
    'isInverseSide' => false,
]);
$metadata->mapField([
    'name' => 'fullName',
    'fieldName' => 'fullName',
    'type' => Type::STRING,
    'nullable' => false,
    'options' => [],
    'strategy' => ClassMetadata::STORAGE_STRATEGY_SET,
    'notSaved' => false,
    'enumType' => null,
    'isCascadeRemove' => false,
    'isCascadePersist' => false,
    'isCascadeRefresh' => false,
    'isCascadeMerge' => false,
    'isCascadeDetach' => false,
    'isOwningSide' => true,
    'isInverseSide' => false,
]);
$metadata->mapField([
    'name' => 'username',
    'fieldName' => 'username',
    'type' => Type::STRING,
    'nullable' => false,
    'options' => [],
    'strategy' => ClassMetadata::STORAGE_STRATEGY_SET,
    'notSaved' => false,
    'enumType' => null,
    'isCascadeRemove' => false,
    'isCascadePersist' => false,
    'isCascadeRefresh' => false,
    'isCascadeMerge' => false,
    'isCascadeDetach' => false,
    'isOwningSide' => true,
    'isInverseSide' => false,
]);
$metadata->mapField([
    'name' => 'email',
    'fieldName' => 'email',
    'type' => Type::STRING,
    'nullable' => false,
    'options' => [],
    'strategy' => ClassMetadata::STORAGE_STRATEGY_SET,
    'notSaved' => false,
    'enumType' => null,
    'isCascadeRemove' => false,
    'isCascadePersist' => false,
    'isCascadeRefresh' => false,
    'isCascadeMerge' => false,
    'isCascadeDetach' => false,
    'isOwningSide' => true,
    'isInverseSide' => false,
]);
$metadata->mapField([
    'name' => 'password',
    'fieldName' => 'password',
    'type' => Type::STRING,
    'nullable' => false,
    'options' => [],
    'strategy' => ClassMetadata::STORAGE_STRATEGY_SET,
    'notSaved' => false,
    'enumType' => null,
    'isCascadeRemove' => false,
    'isCascadePersist' => false,
    'isCascadeRefresh' => false,
    'isCascadeMerge' => false,
    'isCascadeDetach' => false,
    'isOwningSide' => true,
    'isInverseSide' => false,
]);
$metadata->mapField([
    'name' => 'roles',
    'fieldName' => 'roles',
    'type' => Type::COLLECTION,
    'nullable' => false,
    'options' => [],
    'strategy' => ClassMetadata::STORAGE_STRATEGY_SET,
    'notSaved' => false,
    'enumType' => null,
    'isCascadeRemove' => false,
    'isCascadePersist' => false,
    'isCascadeRefresh' => false,
    'isCascadeMerge' => false,
    'isCascadeDetach' => false,
    'isOwningSide' => true,
    'isInverseSide' => false,
]);
