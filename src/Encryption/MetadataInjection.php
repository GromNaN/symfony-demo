<?php

namespace App\Encryption;

use App\Encryption\DataEncryptionKey\DataEncryptionKey;
use App\Encryption\DataEncryptionKey\DataEncryptionKeyStore;
use App\Entity\User;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[Autoconfigure(public: true)]
class MetadataInjection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(env: 'DATA_ENCRYPTION_KEY')]
        private string $dek,
        private Encryptor $encryptor,
    ) {
    }

    public function boot(): void
    {
        foreach ($this->getColumns() as $class => $columns) {
            $metadata = $this->entityManager->getClassMetadata($class);

            $typeRegistry = Type::getTypeRegistry();
            foreach ($columns as $column => $options) {
                $mapping = $metadata->getFieldMapping($column);
                if (str_starts_with($mapping->type, 'encrypted_')) {
                    continue;
                }

                $dekId = $options['dekId'] ?? sprintf('%s::%s', $class, $column);
                $typeName = sprintf('encrypted_%s_%s', $class, $column);

                $typeRegistry->{$typeRegistry->has($typeName) ? 'override' : 'register'}($typeName, new EncryptedType(
                    $typeRegistry->get($mapping->type),
                    new DekEncryptionService(
                        $this->buildStaticStore(),
                        $this->encryptor,
                    ),
                    $dekId,
                    $options['deterministic']
                ));

                $mapping->type = $typeName;
                $metadata->fieldMappings[$column] = $mapping;
            }
        }
    }

    /**
     * Provide a plain in-memory DEK for metadata-level type registration.
     */
    private function buildStaticStore(): DataEncryptionKeyStore
    {
        return new class($this->dek) implements DataEncryptionKeyStore {
            public function __construct(private readonly string $dek)
            {
            }

            public function getKey(string $id): DataEncryptionKey
            {
                return new DataEncryptionKey($id, null, null, $this->dek);
            }
        };
    }

    private function getColumns(): array
    {
        return [
            User::class => [
                'email' => [
                    'deterministic' => true,
                ],
                'firstName' => [
                    'deterministic' => false,
                ],
                'lastName' => [
                    'deterministic' => false,
                ],
                'birthday' => [
                    'deterministic' => false,
                ],
            ],
        ];
    }
}
