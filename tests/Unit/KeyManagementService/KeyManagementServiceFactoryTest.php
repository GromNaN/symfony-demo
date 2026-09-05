<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Unit\KeyManagementService;

use Gromnan\DoctrineEncrypt\KeyManagementService\KeyManagementServiceFactory;
use Gromnan\DoctrineEncrypt\KeyManagementService\LocalKeyManagementService;
use PHPUnit\Framework\TestCase;

class KeyManagementServiceFactoryTest extends TestCase
{
    public function testCreateLocal(): void
    {
        $kms = KeyManagementServiceFactory::createLocal();

        $this->assertInstanceOf(LocalKeyManagementService::class, $kms);
    }

    public function testCreateLocalWithMasterKey(): void
    {
        $masterKey = base64_encode('test-master-key-32-bytes-long-key');
        $kms = KeyManagementServiceFactory::createLocal($masterKey);

        $this->assertInstanceOf(LocalKeyManagementService::class, $kms);
        $this->assertTrue($kms->masterKeyExists('default'));
    }

    public function testCreateFromConfigLocal(): void
    {
        $config = [
            'provider' => 'local',
            'master_key' => base64_encode('test-key-32-bytes-long-for-testing'),
        ];

        $kms = KeyManagementServiceFactory::createFromConfig($config);

        $this->assertInstanceOf(LocalKeyManagementService::class, $kms);
    }

    public function testCreateFromConfigWithInvalidProvider(): void
    {
        $config = ['provider' => 'invalid-provider'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported KMS provider: invalid-provider');

        KeyManagementServiceFactory::createFromConfig($config);
    }

    public function testCreateFromConfigWithoutProvider(): void
    {
        $config = [];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('provider is required');

        KeyManagementServiceFactory::createFromConfig($config);
    }

    public function testCreateAwsFromConfigWithInvalidConfig(): void
    {
        $config = [
            'provider' => 'aws',
            'invalid_option' => 'test',
        ];

        $this->expectException(\AsyncAws\Core\Exception\InvalidArgument::class);

        KeyManagementServiceFactory::createFromConfig($config);
    }

    public function testCreateGoogleCloudFromConfigThrowsExceptionWithoutCredentials(): void
    {
        $config = [
            'provider' => 'gcp',
            'project_id' => 'test-project',
        ];

        $this->expectException(\Google\ApiCore\ValidationException::class);
        $this->expectExceptionMessage('Could not construct ApplicationDefaultCredentials');

        KeyManagementServiceFactory::createFromConfig($config);
    }


    public function testCreateAzureFromConfigWithoutVaultUrl(): void
    {
        $config = ['provider' => 'azure'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vault_url is required for Azure Key Vault');

        KeyManagementServiceFactory::createFromConfig($config);
    }

    public function testCreateKmipFromConfigWithoutServerUrl(): void
    {
        $config = ['provider' => 'kmip'];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('server_url is required for KMIP');

        KeyManagementServiceFactory::createFromConfig($config);
    }

    public function testCreateKmipFromConfigWithoutAuthentication(): void
    {
        $config = [
            'provider' => 'kmip',
            'server_url' => 'https://kmip.example.com',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('KMIP requires either certificate or username/password authentication');

        KeyManagementServiceFactory::createFromConfig($config);
    }
}
