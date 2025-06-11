<?php

declare(strict_types=1);

namespace App\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Document;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Encrypt;
use Doctrine\ODM\MongoDB\Mapping\Annotations\EncryptQuery;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Field;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Id;
use Doctrine\ODM\MongoDB\Types\Type;
use MongoDB\BSON\Decimal128;

/**
 * Test all supported types for range encrypted queries.
 *
 * @see https://www.mongodb.com/docs/manual/core/queryable-encryption/reference/supported-operations/#supported-and-unsupported-bson-types
 */
#[Document]
class RangeTypes
{
    #[Id]
    public string $id;

    #[Field(type: Type::INT)]
    #[Encrypt(EncryptQuery::Range, min: 5, max: 10)]
    public int $intField;

    #[Field(type: Type::FLOAT)]
    #[Encrypt(EncryptQuery::Range, min: 5.5, max: 10.5)]
    public float $floatField;

    #[Field(type: Type::DECIMAL128)]
    #[Encrypt(EncryptQuery::Range, min: new Decimal128('0.1'), max: new Decimal128('0.2'))]
    public Decimal128 $decimalField;

    #[Field(type: Type::DATE_IMMUTABLE)]
    #[Encrypt(EncryptQuery::Range, min: new DateTimeImmutable('2000-01-01 00:00:00'), max: new DateTimeImmutable('2100-01-01 00:00:00'))]
    public DateTimeImmutable $dateField;
}
