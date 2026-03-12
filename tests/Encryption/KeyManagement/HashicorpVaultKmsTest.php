<?php

declare(strict_types=1);

namespace App\Tests\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\KeyManagement\HashicorpVaultKms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HashicorpVaultKms::class)]
final class HashicorpVaultKmsTest extends TestCase
{
    public function testEncryptUpdatesKeyWithMasterKeyIdAndEncryptedDek(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringEndsWith('/v1/transit/encrypt/talk-encryption-master', $url);
            self::assertSame('X-Vault-Token: root', $options['normalized_headers']['x-vault-token'][0]);
            self::assertSame(base64_encode('plain-dek'), json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR)['plaintext']);

            return new MockResponse(json_encode([
                'data' => [
                    'ciphertext' => 'vault:v1:encrypted-dek',
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $kms = new HashicorpVaultKms(
            masterKeyId: 'talk-encryption-master',
            vaultBaseUrl: 'http://vault:8200',
            vaultToken: 'root',
            httpClient: $client,
        );

        $dek = new DataEncryptionKey('dek-1', null, null, 'plain-dek');
        $kms->encrypt($dek);

        self::assertSame('talk-encryption-master', $dek->getMasterKeyId());
        self::assertSame('vault:v1:encrypted-dek', $dek->getEncryptedDek());
    }

    public function testDecryptUpdatesKeyWithPlainDek(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertStringEndsWith('/v1/transit/decrypt/talk-encryption-master', $url);
            self::assertSame('X-Vault-Token: root', $options['normalized_headers']['x-vault-token'][0]);
            self::assertSame('vault:v1:encrypted-dek', json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR)['ciphertext']);

            return new MockResponse(json_encode([
                'data' => [
                    'plaintext' => base64_encode('plain-dek'),
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $kms = new HashicorpVaultKms(
            masterKeyId: 'talk-encryption-master',
            vaultBaseUrl: 'http://vault:8200',
            vaultToken: 'root',
            httpClient: $client,
        );

        $dek = new DataEncryptionKey('dek-1', 'talk-encryption-master', 'vault:v1:encrypted-dek');
        $kms->decrypt($dek);

        self::assertSame('plain-dek', $dek->getPlainDek());
    }
}
