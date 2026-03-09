<?php

declare(strict_types=1);

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\UserType;
use Symfony\Component\Form\Test\TypeTestCase;

class UserTypeTest extends TypeTestCase
{
    public function testSubmitValidData(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'plainPassword' => [
                'first' => 'SecurePassword123!',
                'second' => 'SecurePassword123!',
            ],
        ];

        $user = new User();
        $form = $this->factory->create(UserType::class, $user);

        // Submit the data to the form directly
        $form->submit($formData);

        self::assertTrue($form->isSynchronized());

        // Verify form has the extra fields (unmapped)
        self::assertTrue($form->has('email'));
        self::assertTrue($form->has('firstName'));
        self::assertTrue($form->has('lastName'));
        self::assertTrue($form->has('plainPassword'));

        // Get the submitted data
        self::assertSame('test@example.com', $form->get('email')->getData());
        self::assertSame('John', $form->get('firstName')->getData());
        self::assertSame('Doe', $form->get('lastName')->getData());
        self::assertSame('SecurePassword123!', $form->get('plainPassword')->getData());
    }

    public function testFormWithoutPassword(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
        ];

        $user = new User();
        $form = $this->factory->create(UserType::class, $user, [
            'include_password' => false,
        ]);

        $form->submit($formData);

        self::assertTrue($form->isSynchronized());
        self::assertFalse($form->has('plainPassword'));
        self::assertTrue($form->has('email'));
    }

    public function testPasswordMismatchValidation(): void
    {
        $formData = [
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'plainPassword' => [
                'first' => 'SecurePassword123!',
                'second' => 'DifferentPassword123!',
            ],
        ];

        $form = $this->factory->create(UserType::class);
        $form->submit($formData);

        self::assertFalse($form->isValid());
        self::assertNotEmpty($form->get('plainPassword')->getErrors());
    }

    public function testFormStructure(): void
    {
        $form = $this->factory->create(UserType::class);

        // Check that all expected fields are present
        self::assertTrue($form->has('email'));
        self::assertTrue($form->has('firstName'));
        self::assertTrue($form->has('lastName'));
        self::assertTrue($form->has('plainPassword'));
    }

    public function testFormStructureWithoutPassword(): void
    {
        $form = $this->factory->create(UserType::class, null, [
            'include_password' => false,
        ]);

        // Check that the password field is not present
        self::assertFalse($form->has('plainPassword'));
        self::assertTrue($form->has('email'));
        self::assertTrue($form->has('firstName'));
        self::assertTrue($form->has('lastName'));
    }
}

