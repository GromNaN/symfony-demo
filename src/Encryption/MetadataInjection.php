<?php

namespace App\Encryption;

use App\Entity\UserEncrypted;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * MetadataInjection rewrites Doctrine metadata to register encrypted DBAL types.
 */
#[Autoconfigure(public: true)]
class MetadataInjection
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DekEncryptionService $dekEncryptionService,
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
                    $this->dekEncryptionService,
                    $dekId,
                    $options['deterministic']
                ));

                $mapping->type = $typeName;
                $metadata->fieldMappings[$column] = $mapping;
            }
        }
    }

    private function getColumns(): array
    {
        return [
            UserEncrypted::class => [
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
