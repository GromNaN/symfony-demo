<?php

declare(strict_types=1);

namespace App\Listener;

use App\Entity\UserEmailEncrypted;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

#[AsDoctrineListener(event: \Doctrine\ORM\Events::prePersist)]
#[AsDoctrineListener(event: \Doctrine\ORM\Events::preUpdate)]
final class UserEmailEncryptedHashListener
{
    public function __construct(private readonly string $hmacKey) {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $this->applyHashes($args->getObject());
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $this->applyHashes($args->getObject());

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        $metadata = $em->getClassMetadata(UserEmailEncrypted::class);
        $uow->recomputeSingleEntityChangeSet($metadata, $args->getObject());
    }

    private function applyHashes(UserEmailEncrypted $entity): void
    {
        $normalizedEmail = strtolower(trim($entity->email));
        $entity->emailHash = hash_hmac('sha256', $normalizedEmail, $this->hmacKey);

        $domain = explode('@', $normalizedEmail, 2)[1] ?? '';
        $entity->emailDomainHash = hash_hmac('sha256', $domain, $this->hmacKey);
    }
}
