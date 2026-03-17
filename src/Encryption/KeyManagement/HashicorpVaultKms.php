<?php

declare(strict_types=1);

namespace App\Encryption\KeyManagement;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HashicorpVaultKms wraps and unwraps DEKs through HashiCorp Vault Transit.
 */
class HashicorpVaultKms implements KmsInterface
{
    private readonly HttpClientInterface $httpClient;

    public function __construct(
        private readonly string $masterKeyId,
        private readonly string $vaultBaseUrl = 'http://vault:8200',
        private readonly string $vaultToken = 'root',
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? HttpClient::create();
    }

    public function encrypt(DataEncryptionKey $key): DataEncryptionKey
    {
        $response = $this->request(
            $this->buildTransitUrl('encrypt'),
            ['plaintext' => base64_encode($key->getPlainDek())]
        );

        $ciphertext = $response['data']['ciphertext'] ?? null;

        if (!is_string($ciphertext) || $ciphertext === '') {
            throw new \RuntimeException('Vault encrypt response does not contain a valid ciphertext.');
        }

        $key->encrypt($this->masterKeyId, $ciphertext);

        return $key;
    }

    public function decrypt(DataEncryptionKey $key): DataEncryptionKey
    {
        $response = $this->request(
            $this->buildTransitUrl('decrypt'),
            ['ciphertext' => $key->getEncryptedDek()]
        );

        $plaintext = $response['data']['plaintext'] ?? null;

        if (!is_string($plaintext) || $plaintext === '') {
            throw new \RuntimeException('Vault decrypt response does not contain a valid plaintext.');
        }

        $plainDek = base64_decode($plaintext, true);

        if ($plainDek === false) {
            throw new \RuntimeException('Vault plaintext is not valid base64.');
        }

        $key->decrypt($plainDek);

        return $key;
    }

    private function buildTransitUrl(string $operation): string
    {
        return rtrim($this->vaultBaseUrl, '/') . '/v1/transit/' . $operation . '/' . $this->masterKeyId;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(string $url, array $payload): array
    {
        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Accept' => 'application/json',
                    'X-Vault-Token' => $this->vaultToken,
                ],
                'json' => $payload,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Vault request failed: transport error.', previous: $e);
        }

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getContent(false);

        if ($statusCode >= 400) {
            throw new \RuntimeException(sprintf(
                'Vault request failed with HTTP %d: %s',
                $statusCode,
                $responseBody
            ));
        }

        $decoded = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Vault response is not a JSON object.');
        }

        return $decoded;
    }
}
