<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\KeyManagement\BlindIndexInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class EmailBlindIndexUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepository $users,
        #[Autowire(service: 'app.index.user_email')]
        private readonly BlindIndexInterface $index,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->users->findOneBy(['emailIndex' => $this->index->of($identifier)])
            ?? throw new UserNotFoundException(\sprintf('No user found for the email "%s".', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->users->find($user->getId())
            ?? throw new UserNotFoundException();
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
