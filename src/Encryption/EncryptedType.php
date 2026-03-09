<?php

declare(strict_types=1);

namespace App\Encryption;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

final class EncryptedType extends Type
{
    private readonly Encryptor $encryptor;
    private readonly string $dek;

    public function __construct(
        private readonly Type $parentType,
        string $dek,
        private readonly bool $deterministic,
    ) {
        $this->encryptor = new Encryptor();
        $this->dek = $this->normalizeDek($dek);
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getBinaryTypeDeclarationSQL($column);
    }

    public function getBindingType(): ParameterType
    {
        return ParameterType::BINARY;
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

        $payload = $this->deterministic
            ? $this->encryptor->encryptDeterministic($parentValue, $this->dek)
            : $this->encryptor->encryptRandom($parentValue, $this->dek);

        return $payload;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): mixed
    {
        if ($value === null || $value === '') {
            return $this->parentType->convertToPHPValue(null, $platform);
        }

        if (!is_string($value)) {
            throw new ConversionException(sprintf(
                'Cannot decrypt value of type %s for parent DBAL type %s. Expected string or null.',
                get_debug_type($value),
                $this->parentType->getName()
            ));
        }

        $payload = $value;

        if ($payload === false) {
            throw new ConversionException(sprintf(
                'Invalid encrypted payload for parent DBAL type %s: value is not valid base64.',
                $this->parentType->getName()
            ));
        }

        $plaintext = $this->encryptor->decrypt($payload, $this->dek);

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

    private function normalizeDek(string $dek): string
    {
        if (strlen($dek) === 64 && ctype_xdigit($dek)) {
            $decoded = hex2bin($dek);

            if ($decoded !== false) {
                $dek = $decoded;
            }
        }

        if (strlen($dek) !== 32) {
            throw new \InvalidArgumentException('DEK must be 32 raw bytes or a 64-char hex string.');
        }

        return $dek;
    }
}
