# UserType Form Usage

## Overview

The `UserType` form handles creating and editing users with encrypted fields. All sensitive fields (email, firstName, lastName) are unmapped and must be manually encrypted/decrypted in the controller.

## Features

- **Unmapped Fields**: All encrypted fields are `mapped => false` to prevent automatic binding
- **Conditional Password Field**: Use `include_password` option to show/hide password field
- **Password Confirmation**: Uses `RepeatedType` to ensure password is typed correctly twice

## Creating a New User

```php
use App\Form\UserType;
use App\Entity\UserEncrypted;

public function new(Request $request): Response
{
    $user = new UserEncrypted();
    $form = $this->createForm(UserType::class, $user);
    
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        $dek = $_ENV['DATA_ENCRYPTION_KEY'];
        
        // Encrypt fields manually
        $user->email = base64_encode(
            $this->encryptor->encryptDeterministic($form->get('email')->getData(), $dek)
        );
        $user->firstName = base64_encode(
            $this->encryptor->encryptRandom($form->get('firstName')->getData(), $dek)
        );
        $user->lastName = base64_encode(
            $this->encryptor->encryptRandom($form->get('lastName')->getData(), $dek)
        );
        
        // Hash password
        $user->password = $this->passwordHasher->hashPassword(
            $user,
            $form->get('plainPassword')->getData()
        );
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $this->redirectToRoute('user_list');
    }
    
    return $this->render('user/new.html.twig', ['form' => $form]);
}
```

## Editing an Existing User

```php
public function edit(Request $request, User $user): Response
{
    $dek = $_ENV['DATA_ENCRYPTION_KEY'];
    
    // Create form without password field
    $form = $this->createForm(UserType::class, $user, [
        'include_password' => false,
    ]);
    
    // Decrypt and populate form fields
    $form->get('email')->setData(
        $this->encryptor->decrypt(base64_decode($user->email, true), $dek)
    );
    $form->get('firstName')->setData(
        $this->encryptor->decrypt(base64_decode($user->firstName, true), $dek)
    );
    $form->get('lastName')->setData(
        $this->encryptor->decrypt(base64_decode($user->lastName, true), $dek)
    );
    
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        // Re-encrypt fields
        $user->email = base64_encode(
            $this->encryptor->encryptDeterministic($form->get('email')->getData(), $dek)
        );
        $user->firstName = base64_encode(
            $this->encryptor->encryptRandom($form->get('firstName')->getData(), $dek)
        );
        $user->lastName = base64_encode(
            $this->encryptor->encryptRandom($form->get('lastName')->getData(), $dek)
        );
        
        $this->entityManager->flush();
        
        return $this->redirectToRoute('user_list');
    }
    
    return $this->render('user/edit.html.twig', ['form' => $form]);
}
```

## Form Options

- `include_password` (default: `true`): Whether to include the password field in the form

## Important Notes

1. **Never map encrypted fields directly** - Always use `mapped => false`
2. **Encrypt in controller** - Encryption happens in the controller, not in the form or entity
3. **Deterministic for searchable fields** - Use `encryptDeterministic()` for email (unique constraint)
4. **Random for sensitive fields** - Use `encryptRandom()` for firstName and lastName
5. **Base64 encode** - Always base64 encode encrypted data before storing in text columns
6. **DEK management** - Store DEK securely in environment variables or KMS

