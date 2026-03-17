<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\UserQe;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class UserQeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UserQe::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        // Plain QE inputs captured by the subscriber before flush.
        yield DateTimeField::new('birthdate', 'Birthdate');
        yield IntegerField::new('yearlyIncome', 'Yearly Income');

        // Stored encrypted payloads and search tags.
        yield TextField::new('birthdateCipher', 'Birthdate Cipher')->hideOnForm();
        yield TextField::new('yearlyIncomeCipher', 'Yearly Income Cipher')->hideOnForm();
        yield ArrayField::new('safeContent', 'Safe Content')->hideOnForm();
    }
}

