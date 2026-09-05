<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService;

/**
 * Exception thrown by Key Management Service implementations
 */
class KeyManagementException extends \Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $keyId = null,
        public readonly ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function keyNotFound(string $keyId): self
    {
        return new self(
            message: "Key not found: {$keyId}",
            code: 404,
            keyId: $keyId
        );
    }

    public static function keyCreationFailed(string $keyId, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Failed to create key: {$keyId}",
            code: 500,
            previous: $previous,
            keyId: $keyId
        );
    }

    public static function decryptionFailed(string $keyId, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Failed to decrypt with key: {$keyId}",
            code: 500,
            previous: $previous,
            keyId: $keyId
        );
    }

    public static function encryptionFailed(string $keyId, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Failed to encrypt with key: {$keyId}",
            code: 500,
            previous: $previous,
            keyId: $keyId
        );
    }

    public static function invalidKeyLength(int $length): self
    {
        return new self(
            message: "Invalid key length: {$length} bytes. Must be between 1 and 256 bytes.",
            code: 400
        );
    }

    public static function serviceUnavailable(string $service, ?\Throwable $previous = null): self
    {
        return new self(
            message: "Key management service unavailable: {$service}",
            code: 503,
            previous: $previous,
            context: ['service' => $service]
        );
    }
}
