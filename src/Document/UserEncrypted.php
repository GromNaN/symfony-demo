<?php

declare(strict_types=1);

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;
use Doctrine\ODM\MongoDB\Mapping\EncryptQuery;

#[ODM\Document]
class UserEncrypted
{
    #[ODM\Id]
    public ?int $id = null;

    #[ODM\Field]
    #[ODM\Encrypt]
    public string $name;

    #[ODM\Field]
    #[ODM\Encrypt(queryType: EncryptQuery::Equality)]
    public string $email;

    #[ODM\Field]
    #[ODM\Encrypt(
        queryType: EncryptQuery::Range,
        min: new \DateTimeImmutable('1900-01-01'),
        max: new \DateTimeImmutable('2030-01-01'),
    )]
    public \DateTimeImmutable $birthday;

    // Password is hashed using Symfony's password hasher (bcrypt/argon2).
    #[ODM\Field]
    public string $password;

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
