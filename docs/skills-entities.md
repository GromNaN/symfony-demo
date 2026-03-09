# Entity Authoring Skills

## Goals

- Use public properties on entities.
- Do not add getters or setters (except for `getPassword()` required by `PasswordAuthenticatedUserInterface`).
- Store encrypted values directly in properties.

## Encryption rules

- Email must be deterministically encrypted so it can be indexed uniquely.
- First name must be randomly encrypted to avoid correlating identical values.
- Last name must be randomly encrypted to avoid correlating identical values.
- Store ciphertext as Base64 when the column type is text.

## Password hashing

- Passwords are hashed (not encrypted) using Symfony's password hasher component.
- The User entity must implement `PasswordAuthenticatedUserInterface`.
- Use bcrypt or argon2 algorithm (configured via `security.yaml`).
- Store the hashed password in the `$password` property.

## Forms

- All encrypted fields must be `mapped => false` in the form type.
- Encryption/decryption happens in the controller, not in the form type.
- Use `include_password` option to conditionally include password field (e.g., false when editing).
- Form fields should match the plaintext data, not the encrypted storage format.

## Example (User)

```php
<?php

use App\Encryption\Encryptor;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

$encryptor = new Encryptor();
$dek = random_bytes(32);

$user = new User();
$user->email = base64_encode($encryptor->encryptDeterministic('sarah@example.test', $dek));
$user->firstName = base64_encode($encryptor->encryptRandom('Sarah', $dek));
$user->lastName = base64_encode($encryptor->encryptRandom('Connor', $dek));

// Hash the password using Symfony's password hasher
$user->password = $passwordHasher->hashPassword($user, 'PlainTextPassword123!');
```

## Example (UserType Form)

```php
<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'mapped' => false, // Not mapped to entity
                'required' => true,
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'mapped' => false,
                'required' => true,
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'mapped' => false,
                'required' => true,
            ]);

        if ($options['include_password']) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Repeat Password'],
                'invalid_message' => 'The password fields must match.',
                'mapped' => false,
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'include_password' => true,
        ]);
    }
}
```

## Notes

- Decryption is done at the edges of the application (controllers/services).
- The entity stores encrypted data only, not plaintext.
- Password hashing is one-way: you cannot decrypt a hashed password.
- Controllers handle encryption/decryption when processing forms.
