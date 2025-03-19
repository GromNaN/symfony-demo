<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $emails = ['nicolas@cuisine.dev', 'fabien@cuisine.dev', 'eloise@cuisine.dev', 'camille@cuisine.dev', 'chef@cuisine.dev'];

        foreach ($emails as $email) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('Délice');
            $user->setRoles(['ROLE_USER']);
            $manager->persist($user);

            $this->addReference($email, $user);
        }

        $manager->flush();
    }
}
