<?php

declare(strict_types=1);

namespace App\Controller;

use App\Encryption\Encryptor;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user', name: 'user_')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly Encryptor $encryptor,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get the Data Encryption Key (DEK) from environment.
     * The DEK should be a 32-byte hex-encoded string.
     */
    private function getDek(): string
    {
        $dekHex = $_ENV['DATA_ENCRYPTION_KEY'] ?? throw new \RuntimeException('DATA_ENCRYPTION_KEY environment variable is not set');

        // Convert hex to binary (32 bytes for AES-256)
        $dek = hex2bin($dekHex);

        if ($dek === false || strlen($dek) !== 32) {
            throw new \RuntimeException('DATA_ENCRYPTION_KEY must be a 64-character hex string (32 bytes)');
        }

        return $dek;
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function list(UserRepository $userRepository): Response
    {
        // Retrieve all users (without decryption - just show encrypted storage)
        $users = $userRepository->findAll();

        return $this->render('user/list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the DEK from environment
            $dek = $this->getDek();

            // Encrypt form data before persisting
            $user->email = base64_encode(
                $this->encryptor->encryptDeterministic($form->get('email')->getData(), $dek)
            );
            $user->firstName = base64_encode(
                $this->encryptor->encryptRandom($form->get('firstName')->getData(), $dek)
            );
            $user->lastName = base64_encode(
                $this->encryptor->encryptRandom($form->get('lastName')->getData(), $dek)
            );

            // Hash the password
            $user->password = $this->passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            );

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'User created successfully.');

            return $this->redirectToRoute('user_list');
        }

        return $this->render('user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        // Get the DEK from environment
        $dek = $this->getDek();

        // Decrypt existing data to populate the form
        $form = $this->createForm(UserType::class, $user, [
            'include_password' => false, // Don't require password when editing
        ]);

        // Set decrypted values to the form
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
            // Re-encrypt form data before persisting
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

            $this->addFlash('success', 'User updated successfully.');

            return $this->redirectToRoute('user_list');
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        // Verify CSRF token if using forms
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('user_list');
    }
}

