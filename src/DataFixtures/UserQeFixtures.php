<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\UserQueryable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class UserQeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $rows = [
            [new \DateTimeImmutable('1990-03-15T00:00:00+00:00'), 45000],
            [new \DateTimeImmutable('1985-07-22T00:00:00+00:00'), 72000],
            [new \DateTimeImmutable('1995-11-08T00:00:00+00:00'), 38500],
        ];

        foreach ($rows as [$birthdate, $yearlyIncome]) {
            $user = new UserQueryable();
            $user->birthdate = $birthdate;
            $user->yearlyIncome = $yearlyIncome;

            // QueryableEncryptionSubscriber fills ciphertext and safeContent tags.
            $manager->persist($user);
        }

        $manager->flush();
    }
}

