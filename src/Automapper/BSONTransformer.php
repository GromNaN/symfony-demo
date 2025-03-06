<?php

namespace App\Automapper;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class BSONTransformer
{
    public static function transformObjectIdToSting(ObjectId $value): string
    {
        return (string) $value;
    }

    public static function transformStringToObjectId(string $value): ObjectId
    {
        return new ObjectId($value);
    }

    public static function transformDateTimeToUTCDateTime(\DateTimeInterface $value): UTCDateTime
    {
        return new UTCDateTime($value);
    }

    public static function transformUTCDateTimeToDateTimeImmutable(UTCDateTime $value): \DateTimeImmutable
    {
        return $value->toDateTimeImmutable();
    }
}
