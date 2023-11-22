<?php

namespace App\MongoDB\Codec;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Codec\Codec;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\EncodeIfSupported;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

class DateTimeCodec implements Codec
{
    use EncodeIfSupported;
    use DecodeIfSupported;

    public function canDecode($value): bool
    {
        return $value instanceof UTCDateTime;
    }

    public function decode($value): \DateTimeImmutable
    {
        assert($this->canDecode($value), new \InvalidArgumentException('Expected UTCDateTime'));

        return \DateTimeImmutable::createFromInterface($value->toDateTime());
    }

    public function canEncode($value): bool
    {
        return $value instanceof \DateTimeInterface;
    }

    public function encode($value): UTCDateTime
    {
        assert($this->canEncode($value), new \InvalidArgumentException('Expected DateTimeInterface'));

        return new UTCDateTime($value);
    }
}
