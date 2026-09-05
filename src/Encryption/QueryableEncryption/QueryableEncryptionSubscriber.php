<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

use App\Entity\UserQueryable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * QueryableEncryptionSubscriber populates QE ciphertext and safeContent tags before flush.
 */
#[AsDoctrineListener(event: Events::preFlush)]
final class QueryableEncryptionSubscriber
{
    public function __construct(
        private readonly RangeTagGeneratorFactory $generatorFactory,
    ) {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $processed = [];

        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof UserQueryable) {
                $processed[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($em->getUnitOfWork()->getIdentityMap()[UserQueryable::class] ?? [] as $entity) {
            if ($entity instanceof UserQueryable) {
                $processed[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($processed as $user) {
            $this->encryptUser($user);
        }
    }

    private function encryptUser(UserQueryable $user): void
    {
        $safeContent = [];

        if ($user->birthdate !== null) {
            $generator = $this->generatorFactory->forBirthdate();
            $epochDays = (int) floor($user->birthdate->getTimestamp() / 86400);
            $tags = $generator->generateValueTags((float) $epochDays);
            $safeContent = array_merge($safeContent, array_map(fn($t) => base64_encode($t), $tags));
        }

        if ($user->yearlyIncome !== null) {
            $generator = $this->generatorFactory->forYearlyIncome();
            $tags = $generator->generateValueTags((float) $user->yearlyIncome);
            $safeContent = array_merge($safeContent, array_map(fn($t) => base64_encode($t), $tags));
        }

        $user->safeContent = $safeContent;
    }
}
