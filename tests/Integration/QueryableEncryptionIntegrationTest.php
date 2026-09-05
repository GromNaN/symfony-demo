<?php

declare(strict_types=1);

namespace Gromnan\DoctrineEncrypt\Tests\Integration;

use Gromnan\DoctrineEncrypt\Tests\BaseTestCase;
use Gromnan\DoctrineEncrypt\Tests\Fixtures\User;
use Gromnan\DoctrineEncrypt\Tests\Fixtures\Payment;

class QueryableEncryptionIntegrationTest extends BaseTestCase
{
    public function testEntityPersistenceAndRetrieval(): void
    {
        // Create a test user
        $user = new User(
            name: 'John Doe',
            email: 'john.doe@example.com',
            ssn: '123-45-6789'
        );
        $user->bloodType = 'O+';
        $user->medicalRecords = [
            'allergies' => ['peanuts', 'shellfish'],
            'medications' => ['aspirin'],
            'conditions' => ['hypertension']
        ];

        // Persist the user
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->id;
        $this->assertNotNull($userId);

        // Clear the entity manager to ensure we're loading from database
        $this->entityManager->clear();

        // Retrieve the user
        $retrievedUser = $this->entityManager->find(User::class, $userId);
        $this->assertInstanceOf(User::class, $retrievedUser);

        // Verify all data is correctly decrypted
        $this->assertSame('John Doe', $retrievedUser->name);
        $this->assertSame('john.doe@example.com', $retrievedUser->email);
        $this->assertSame('123-45-6789', $retrievedUser->ssn);
        $this->assertSame('O+', $retrievedUser->bloodType);
        $this->assertSame([
            'allergies' => ['peanuts', 'shellfish'],
            'medications' => ['aspirin'],
            'conditions' => ['hypertension']
        ], $retrievedUser->medicalRecords);
    }

    public function testDeterministicEncryptionConsistency(): void
    {
        // Create two users with the same email
        $user1 = new User('Alice Smith', 'alice@company.com', '111-11-1111');
        $user2 = new User('Alice Johnson', 'alice@company.com', '222-22-2222');

        $this->entityManager->persist($user1);
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        // Get the raw encrypted email values from database
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare('SELECT email FROM users ORDER BY id');
        $result = $stmt->executeQuery();
        $emails = $result->fetchAllAssociative();

        // Emails should be encrypted to the same value (deterministic)
        $this->assertSame($emails[0]['email'], $emails[1]['email']);

        // But SSNs should be different (random encryption)
        $stmt = $conn->prepare('SELECT ssn FROM users ORDER BY id');
        $result = $stmt->executeQuery();
        $ssns = $result->fetchAllAssociative();
        $this->assertNotSame($ssns[0]['ssn'], $ssns[1]['ssn']);
    }

    public function testPaymentEntityEncryption(): void
    {
        $payment = new Payment(
            userId: 1,
            amount: 299.99,
            cardNumber: '4111111111111111'
        );

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $paymentId = $payment->id;
        $this->entityManager->clear();

        $retrievedPayment = $this->entityManager->find(Payment::class, $paymentId);

        $this->assertSame(1, $retrievedPayment->userId);
        $this->assertSame(299.99, $retrievedPayment->amount);
        $this->assertSame('4111111111111111', $retrievedPayment->cardNumber);
        $this->assertSame('1111', $retrievedPayment->cardLast4);
    }

    public function testQueryableEncryptedFieldSearch(): void
    {
        // Create test users
        $users = [
            new User('User 1', 'user1@test.com', '111-11-1111'),
            new User('User 2', 'user2@test.com', '222-22-2222'),
            new User('User 3', 'user1@test.com', '333-33-3333'), // Duplicate email
        ];

        foreach ($users as $user) {
            $user->bloodType = 'A+';
            $this->entityManager->persist($user);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Test searching by deterministically encrypted email
        $encryptedEmail = $this->encryptionService->encrypt(
            'user1@test.com',
            new \Gromnan\DoctrineEncrypt\Mapping\Encrypted(
                algorithm: \Gromnan\DoctrineEncrypt\Mapping\Encrypted::ALGORITHM_DETERMINISTIC,
                keyAltName: 'user-key'
            )
        );

        $dql = 'SELECT u FROM ' . User::class . ' u WHERE u.email = :email';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('email', $encryptedEmail);
        $foundUsers = $query->getResult();

        // Should find both users with user1@test.com email
        $this->assertCount(2, $foundUsers);
        foreach ($foundUsers as $user) {
            $this->assertSame('user1@test.com', $user->email);
        }
    }

    public function testNullValueHandling(): void
    {
        $user = new User('Test User', 'test@example.com', '000-00-0000');
        // bloodType and medicalRecords are nullable and not set

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $userId = $user->id;
        $this->entityManager->clear();

        $retrievedUser = $this->entityManager->find(User::class, $userId);

        $this->assertNull($retrievedUser->bloodType);
        $this->assertNull($retrievedUser->medicalRecords);
    }
}
