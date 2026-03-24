<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\UserEncrypted;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $rows = [
            ['alice@example.com', 'Alice', 'Dupont', new \DateTimeImmutable('1990-03-15'), 'password1'],
            ['bob@example.com', 'Bob', 'Martin', new \DateTimeImmutable('1985-07-22'), 'password2'],
            ['charlie@example.com', 'Charlie', 'Leclerc', new \DateTimeImmutable('1995-11-08'), 'password3'],
        ];

        foreach ($rows as [$email, $firstName, $lastName, $birthday, $plainPassword]) {
            $user = new UserEncrypted();
            $user->email = $email;
            $user->firstName = $firstName;
            $user->lastName = $lastName;
            $user->birthday = $birthday;
            $user->password = $this->passwordHasher->hashPassword($user, $plainPassword);

            $manager->persist($user);
        }

        $manager->flush();
    }
}

