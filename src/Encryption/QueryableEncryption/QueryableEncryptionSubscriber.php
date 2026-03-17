<?php

declare(strict_types=1);

namespace App\Encryption\QueryableEncryption;

use App\Entity\UserQe;
use App\Entity\UsersEsc;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * QueryableEncryptionSubscriber populates QE ciphertext, safe content and ESC rows before flush.
 */
#[AsDoctrineListener(event: Events::preFlush)]
final class QueryableEncryptionSubscriber
{
    public function __construct(
        private readonly RangeQueryableEncryptionService $qe,
    ) {
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $processed = [];

        foreach ($em->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof UserQe) {
                $processed[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($em->getUnitOfWork()->getIdentityMap()[UserQe::class] ?? [] as $entity) {
            if ($entity instanceof UserQe) {
                $processed[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($processed as $user) {
            $this->encryptUser($user, $em);
        }
    }

    private function encryptUser(UserQe $user, EntityManagerInterface $em): void
    {
        $safeContent = [];

        if ($user->birthdate !== null) {
            $payload = $this->qe->encryptBirthdate($user->birthdate);
            $user->birthdateCipher = $payload->ciphertext;
            $safeContent = array_merge($safeContent, $payload->safeContentTags);

            foreach ($user->clearEscEntriesForField(UsersEsc::FIELD_BIRTHDATE) as $oldEsc) {
                $em->remove($oldEsc);
            }

            foreach ($payload->escRows as $row) {
                $esc = new UsersEsc();
                $esc->setFromDescriptor($row);
                $user->addEscEntry($esc);
                $em->persist($esc);
            }
        }

        if ($user->yearlyIncome !== null) {
            $payload = $this->qe->encryptYearlyIncome($user->yearlyIncome);
            $user->yearlyIncomeCipher = $payload->ciphertext;
            $safeContent = array_merge($safeContent, $payload->safeContentTags);

            foreach ($user->clearEscEntriesForField(UsersEsc::FIELD_YEARLY_INCOME) as $oldEsc) {
                $em->remove($oldEsc);
            }

            foreach ($payload->escRows as $row) {
                $esc = new UsersEsc();
                $esc->setFromDescriptor($row);
                $user->addEscEntry($esc);
                $em->persist($esc);
            }
        }

        if ($safeContent !== []) {
            $user->safeContent = $safeContent;
        }
    }
}
