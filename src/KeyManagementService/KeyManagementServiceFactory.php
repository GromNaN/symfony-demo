<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\KeyManagementService;

use Gromnan\DoctrineEncrypt\KeyManagementService\Provider\AwsKeyManagementService;
use Gromnan\DoctrineEncrypt\KeyManagementService\Provider\AzureKeyVaultService;
use Gromnan\DoctrineEncrypt\KeyManagementService\Provider\GoogleCloudKeyManagementService;
use Gromnan\DoctrineEncrypt\KeyManagementService\Provider\KmipKeyManagementService;
use SensitiveParameter;

/**
 * Factory for creating Key Management Service instances
 */
final class KeyManagementServiceFactory
{
    /**
     * Create a local KMS for development/testing
     */
    public static function createLocal(#[SensitiveParameter] ?string $defaultMasterKey = null): LocalKeyManagementService
    {
        return new LocalKeyManagementService($defaultMasterKey);
    }

    /**
     * Create AWS KMS instance
     */
    public static function createAws(object $kmsClient): AwsKeyManagementService
    {
        return new AwsKeyManagementService($kmsClient);
    }

    /**
     * Create AWS KMS instance from configuration
     */
    public static function createAwsFromConfig(array $config): AwsKeyManagementService
    {
        if (!class_exists('\AsyncAws\Kms\KmsClient')) {
            throw new \RuntimeException('AWS KMS requires async-aws/kms package. Run: composer install async-aws/kms');
        }

        $kmsClient = new \AsyncAws\Kms\KmsClient($config);
        return new AwsKeyManagementService($kmsClient);
    }

    /**
     * Create Google Cloud KMS instance
     */
    public static function createGoogleCloud(
        object $kmsClient,
        string $projectId,
        string $locationId = 'global',
        string $keyRingId = 'doctrine-encryption'
    ): GoogleCloudKeyManagementService {
        return new GoogleCloudKeyManagementService($kmsClient, $projectId, $locationId, $keyRingId);
    }

    /**
     * Create Google Cloud KMS instance from configuration
     */
    public static function createGoogleCloudFromConfig(array $config): GoogleCloudKeyManagementService
    {
        if (!class_exists('\Google\Cloud\Kms\V1\Client\KeyManagementServiceClient')) {
            throw new \RuntimeException('Google Cloud KMS requires google/cloud-kms package. Run: composer install google/cloud-kms');
        }

        $projectId = $config['project_id'] ?? throw new \InvalidArgumentException('project_id is required for Google Cloud KMS');
        $locationId = $config['location_id'] ?? 'global';
        $keyRingId = $config['key_ring_id'] ?? 'doctrine-encryption';

        $clientConfig = [];
        if (isset($config['credentials'])) {
            $clientConfig['credentials'] = $config['credentials'];
        }

        $kmsClient = new \Google\Cloud\Kms\V1\Client\KeyManagementServiceClient($clientConfig);
        $service = new GoogleCloudKeyManagementService($kmsClient, $projectId, $locationId, $keyRingId);

        // Ensure key ring exists
        $service->ensureKeyRing();

        return $service;
    }

    /**
     * Create Azure Key Vault instance
     */
    public static function createAzure(
        string $vaultUrl,
        object $keyVaultClient,
        ?string $tenantId = null
    ): AzureKeyVaultService {
        return new AzureKeyVaultService($vaultUrl, $keyVaultClient, $tenantId);
    }

    /**
     * Create Azure Key Vault with managed identity
     */
    public static function createAzureWithManagedIdentity(string $vaultUrl): AzureKeyVaultService
    {
        return AzureKeyVaultService::createWithManagedIdentity($vaultUrl);
    }

    /**
     * Create Azure Key Vault with client credentials
     */
    public static function createAzureWithClientCredentials(
        string $vaultUrl,
        string $tenantId,
        string $clientId,
        #[SensitiveParameter] string $clientSecret
    ): AzureKeyVaultService {
        return AzureKeyVaultService::createWithClientCredentials($vaultUrl, $tenantId, $clientId, $clientSecret);
    }

    /**
     * Create KMIP instance with certificate authentication
     */
    public static function createKmipWithCertificate(
        string $serverUrl,
        string $certificatePath,
        string $privateKeyPath,
        ?string $password = null,
        bool $verifyTls = true
    ): KmipKeyManagementService {
        return KmipKeyManagementService::createWithCertificateAuth(
            $serverUrl,
            $certificatePath,
            $privateKeyPath,
            $password,
            $verifyTls
        );
    }

    /**
     * Create KMIP instance with username/password authentication
     */
    public static function createKmipWithUsername(
        string $serverUrl,
        string $username,
        #[SensitiveParameter] string $password,
        bool $verifyTls = true
    ): KmipKeyManagementService {
        return KmipKeyManagementService::createWithUsernameAuth($serverUrl, $username, $password, $verifyTls);
    }

    /**
     * Create KMS instance from configuration array
     */
    public static function createFromConfig(array $config): KeyManagementServiceInterface
    {
        $provider = $config['provider'] ?? throw new \InvalidArgumentException('provider is required');

        return match ($provider) {
            'local' => self::createLocal($config['master_key'] ?? null),
            'aws', 'aws-kms' => self::createAwsFromConfig($config['aws'] ?? $config),
            'gcp', 'google-cloud', 'google-cloud-kms' => self::createGoogleCloudFromConfig($config['gcp'] ?? $config),
            'azure', 'azure-key-vault' => self::createAzureFromConfigArray($config['azure'] ?? $config),
            'kmip' => self::createKmipFromConfig($config['kmip'] ?? $config),
            default => throw new \InvalidArgumentException("Unsupported KMS provider: {$provider}"),
        };
    }

    private static function createAzureFromConfigArray(array $config): AzureKeyVaultService
    {
        $vaultUrl = $config['vault_url'] ?? throw new \InvalidArgumentException('vault_url is required for Azure Key Vault');

        if (isset($config['client_id'], $config['client_secret'], $config['tenant_id'])) {
            return self::createAzureWithClientCredentials(
                $vaultUrl,
                $config['tenant_id'],
                $config['client_id'],
                $config['client_secret']
            );
        }

        // Default to managed identity
        return self::createAzureWithManagedIdentity($vaultUrl);
    }

    private static function createKmipFromConfig(array $config): KmipKeyManagementService
    {
        $serverUrl = $config['server_url'] ?? throw new \InvalidArgumentException('server_url is required for KMIP');

        if (isset($config['certificate_path'], $config['private_key_path'])) {
            return self::createKmipWithCertificate(
                $serverUrl,
                $config['certificate_path'],
                $config['private_key_path'],
                $config['password'] ?? null,
                $config['verify_tls'] ?? true
            );
        }

        if (isset($config['username'], $config['password'])) {
            return self::createKmipWithUsername(
                $serverUrl,
                $config['username'],
                $config['password'],
                $config['verify_tls'] ?? true
            );
        }

        throw new \InvalidArgumentException('KMIP requires either certificate or username/password authentication');
    }
}
