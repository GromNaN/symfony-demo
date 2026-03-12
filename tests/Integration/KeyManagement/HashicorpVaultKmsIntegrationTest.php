<?php

declare(strict_types=1);

namespace App\Tests\Integration\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\KeyManagement\HashicorpVaultKms;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class HashicorpVaultKmsIntegrationTest extends TestCase
{
    private static string $vaultAddr;
    private static string $vaultToken;
    private static string $masterKeyName;

    public static function setUpBeforeClass(): void
    {
        self::$vaultAddr = $_ENV['VAULT_ADDR'] ?? 'http://127.0.0.1:8200';
        self::$vaultToken = $_ENV['VAULT_TOKEN'] ?? 'root';
        self::$masterKeyName = $_ENV['VAULT_TRANSIT_KEY_NAME'] ?? 'talk-encryption-master';

        if (!self::isVaultReachable()) {
            self::markTestSkipped(sprintf(
                'Vault is not reachable at %s. Start Docker service first (docker compose up -d vault).',
                self::$vaultAddr
            ));
        }

        // Idempotent setup for integration tests.
        self::request('POST', '/v1/sys/mounts/transit', ['type' => 'transit'], allowFailure: true);
        self::request('POST', '/v1/transit/keys/' . self::$masterKeyName, null, allowFailure: true);
    }

    public function testEncryptThenDecryptRoundTripUsingRealVault(): void
    {
        $kms = new HashicorpVaultKms(
            masterKeyId: self::$masterKeyName,
            vaultBaseUrl: self::$vaultAddr,
            vaultToken: self::$vaultToken,
        );

        $plainDek = random_bytes(32);

        $source = new DataEncryptionKey('dek-integration-source', null, null, $plainDek);
        $kms->encrypt($source);

        $encryptedDek = $source->getEncryptedDek();
        self::assertNotSame('', $encryptedDek);
        self::assertStringStartsWith('vault:v', $encryptedDek);
        self::assertSame(self::$masterKeyName, $source->getMasterKeyId());

        $target = new DataEncryptionKey('dek-integration-target', self::$masterKeyName, $encryptedDek);
        $kms->decrypt($target);

        self::assertSame($plainDek, $target->getPlainDek());
    }

    private static function isVaultReachable(): bool
    {
        try {
            self::request('GET', '/v1/sys/health', null, allowFailure: true);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, ?array $payload = null, bool $allowFailure = false): array
    {
        $headers = [
            'Accept: application/json',
            'X-Vault-Token: ' . self::$vaultToken,
        ];

        $body = null;
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'ignore_errors' => true,
            ],
        ]);

        $url = rtrim(self::$vaultAddr, '/') . $path;
        $responseBody = file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        if (!is_string($responseBody)) {
            throw new \RuntimeException('Vault HTTP request failed: empty response body.');
        }

        if (!preg_match('/\s(\d{3})\s/', $statusLine, $matches)) {
            throw new \RuntimeException('Vault HTTP request failed: unreadable status code.');
        }

        $statusCode = (int) $matches[1];
        if (!$allowFailure && $statusCode >= 400) {
            throw new \RuntimeException(sprintf('Vault HTTP %d: %s', $statusCode, $responseBody));
        }

        if ($responseBody === '') {
            return [];
        }

        $decoded = json_decode($responseBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}

