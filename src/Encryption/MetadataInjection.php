<?php

namespace App\Encryption;

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
    ) {
    }

    public function boot()
    {
        foreach ($this->getColumns() as $class => $columns) {
            $metadata = $this->entityManager->getClassMetadata($class);

            $typeRegistry = Type::getTypeRegistry();
            foreach ($columns as $column => $options) {
                $mapping = $metadata->getFieldMapping($column);
                if (str_starts_with($mapping->type, 'encrypted_')) {
                    continue;
                }

                $typeName = sprintf('encrypted_%s_%s', $class, $column);
                $typeRegistry->{$typeRegistry->has($typeName) ? 'override' : 'register'}($typeName, new EncryptedType(
                    $typeRegistry->get($mapping->type),
                    $this->dek,
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
