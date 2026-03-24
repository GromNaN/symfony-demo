<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\UserEncrypted;
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
                'mapped' => false,
                'required' => true,
                'help' => 'Email will be encrypted deterministically to allow unique indexing.',
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'mapped' => false,
                'required' => true,
                'help' => 'First name will be encrypted randomly.',
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'mapped' => false,
                'required' => true,
                'help' => 'Last name will be encrypted randomly.',
            ]);

        // Only add password field for new users or when editing
        if ($options['include_password']) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => [
                    'label' => 'Password',
                    'help' => 'Password will be hashed using bcrypt/argon2. Must be at least 8 characters with uppercase, lowercase, and digit.',
                ],
                'second_options' => [
                    'label' => 'Repeat Password',
                ],
                'invalid_message' => 'The password fields must match.',
                'mapped' => false,
                'required' => true,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserEncrypted::class,
            'include_password' => true, // By default, include password field
        ]);
    }
}

