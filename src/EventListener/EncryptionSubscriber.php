<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\EventListener;

use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Common\EventSubscriber;
use Gromnan\DoctrineEncrypt\Encryption\EncryptionService;
use Gromnan\DoctrineEncrypt\Mapping\Encrypted;
use ReflectionClass;

/**
 * Doctrine event subscriber that handles automatic encryption/decryption
 * of entity fields marked with the #[Encrypted] attribute.
 */
final class EncryptionSubscriber implements EventSubscriber
{
    public function __construct(
        private readonly EncryptionService $encryptionService
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::postLoad,
        ];
    }

    /**
     * Encrypt fields before persisting new entities
     */
    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->encryptEntity($args->getObject());
    }

    /**
     * Encrypt fields before updating existing entities
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->encryptEntity($args->getObject());
    }

    /**
     * Decrypt fields after loading entities from database
     */
    public function postLoad(PostLoadEventArgs $args): void
    {
        $this->decryptEntity($args->getObject());
    }

    private function encryptEntity(object $entity): void
    {
        $reflectionClass = new ReflectionClass($entity);

        foreach ($reflectionClass->getProperties() as $property) {
            $encryptedAttribute = $this->getEncryptedAttribute($property);
            if ($encryptedAttribute === null) {
                continue;
            }

            $value = $property->getValue($entity);

            if ($value !== null) {
                $encryptedValue = $this->encryptionService->encrypt($value, $encryptedAttribute);
                $property->setValue($entity, $encryptedValue);
            }
        }
    }

    private function decryptEntity(object $entity): void
    {
        $reflectionClass = new ReflectionClass($entity);

        foreach ($reflectionClass->getProperties() as $property) {
            $encryptedAttribute = $this->getEncryptedAttribute($property);
            if ($encryptedAttribute === null) {
                continue;
            }

            $encryptedValue = $property->getValue($entity);

            if ($encryptedValue !== null && is_string($encryptedValue)) {
                $decryptedValue = $this->encryptionService->decrypt($encryptedValue, $encryptedAttribute);
                $property->setValue($entity, $decryptedValue);
            }
        }
    }

    private function getEncryptedAttribute(\ReflectionProperty $property): ?Encrypted
    {
        $attributes = $property->getAttributes(Encrypted::class);
        return $attributes ? $attributes[0]->newInstance() : null;
    }
}
