<?php

declare(strict_types=1);

namespace App\Encryption;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * EncryptedType is a Doctrine DBAL type wrapper for transparently encrypted fields.
 */
final class EncryptedType extends Type
{
    public function __construct(
        private readonly Type $parentType,
        private readonly DekEncryptionService $dekEncryptionService,
        private readonly string $dekId,
        private readonly bool $deterministic,
    ) {
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBinaryTypeDeclarationSQL($column);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::STRING;
        //return ParameterType::BINARY;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): mixed
    {
        $parentValue = $this->parentType->convertToDatabaseValue($value, $platform);

        if ($parentValue === null) {
            return null;
        }

        if (!is_string($parentValue)) {
            throw new ConversionException(sprintf(
                'Cannot encrypt value of type %s for parent DBAL type %s. Expected string or null.',
                get_debug_type($parentValue),
                $this->parentType->getName()
            ));
        }

        $cyphertext = $this->deterministic
            ? $this->dekEncryptionService->encryptDeterministic($this->dekId, $parentValue)
            : $this->dekEncryptionService->encryptRandom($this->dekId, $parentValue);

        return base64_encode($cyphertext);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return $this->parentType->convertToPHPValue(null, $platform);
        }

        // Some drivers (e.g. pdo_mysql) return encrypted binary data as a stream resource, so we need to read it before decryption.
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!is_string($value)) {
            throw new ConversionException(sprintf(
                'Cannot decrypt value of type %s for parent DBAL type %s. Expected string or null.',
                get_debug_type($value),
                get_debug_type($this->parentType)
            ));
        }

        $value = base64_decode($value);
        $plaintext = $this->dekEncryptionService->decrypt($this->dekId, $value);

        return $this->parentType->convertToPHPValue($plaintext, $platform);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return $this->parentType->requiresSQLCommentHint($platform);
    }

    public function getParentType(): Type
    {
        return $this->parentType;
    }

    public function isDeterministic(): bool
    {
        return $this->deterministic;
    }
}
