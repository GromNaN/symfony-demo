<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Encryption\EncryptedType;
use App\Encryption\MetadataInjection;
use App\Entity\UserEncrypted;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(MetadataInjection::class)]
final class MetadataInjectionTest extends KernelTestCase
{
    public function testBootUpdatesFieldMappings(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(UserEncrypted::class);

        $emailMapping = $metadata->getFieldMapping('email');
        self::assertSame('encrypted_App\Entity\UserEncrypted_email', $emailMapping['type']);
        self::assertInstanceOf(EncryptedType::class, Type::getType('encrypted_App\Entity\UserEncrypted_email'));

        $firstNameMapping = $metadata->getFieldMapping('firstName');
        self::assertSame('encrypted_App\Entity\UserEncrypted_firstName', $firstNameMapping['type']);
        self::assertInstanceOf(EncryptedType::class, Type::getType('encrypted_App\Entity\UserEncrypted_firstName'));

        $lastNameMapping = $metadata->getFieldMapping('lastName');
        self::assertSame('encrypted_App\Entity\UserEncrypted_lastName', $lastNameMapping['type']);
        self::assertInstanceOf(EncryptedType::class, Type::getType('encrypted_App\Entity\UserEncrypted_lastName'));
    }
}

